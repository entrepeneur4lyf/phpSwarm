<?php

declare(strict_types=1);

namespace phpSwarm;

use phpSwarm\Types\Agent;
use phpSwarm\Types\Result;
use phpSwarm\Types\OpenAIModels;
use phpSwarm\Exceptions\FileOperationException;
use phpSwarm\Exceptions\NetworkException;
use phpSwarm\Exceptions\CommandExecutionException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Amp\File;

/**
 * Class SwarmTools
 *
 * This class provides various utility functions for file operations and network requests.
 */
class SwarmTools
{
    private SwarmUtils $utils;
    private Client $httpClient;
    private string $projectRoot;

    /**
     * SwarmTools constructor.
     *
     * @param SwarmUtils $utils Utility class for Swarm operations
     */
    public function __construct(SwarmUtils $utils)
    {
        $this->utils = $utils;
        $this->httpClient = new Client();
        $this->projectRoot = realpath($this->utils::root());
    }

    /**
     * Handle tool calls for an agent.
     *
     * @param array $toolCalls Array of tool calls
     * @param Agent $agent The agent making the tool calls
     * @param array $contextVariables Context variables for the agent
     * @param bool $debug Whether to print debug information
     * @return array Results of the tool calls
     */
    public function handleToolCalls(array $toolCalls, Agent $agent, array $contextVariables, bool $debug): array
    {
        $results = [
            'messages' => [],
            'contextVariables' => [],
            'agent' => null,
        ];

        foreach ($toolCalls as $toolCall) {
            $result = $this->handleSingleToolCall($toolCall, $agent->functions, $contextVariables, $debug);
            $results['messages'][] = $result['message'];
            $results['contextVariables'] = array_merge($results['contextVariables'], $result['contextVariables']);
            if ($result['agent']) {
                $results['agent'] = $result['agent'];
            }
        }

        return $results;
    }

    /**
     * Handle a single tool call.
     *
     * @param object $toolCall The tool call object
     * @param array $functions Available functions
     * @param array $contextVariables Context variables
     * @param bool $debug Whether to print debug information
     * @return array Result of the tool call
     */
    public function handleSingleToolCall($toolCall, $functions, $contextVariables, bool $debug): array
    {
        $functionMap = [
            'listFiles' => [$this, 'listFiles'],
            'readFile' => [$this, 'readFile'],
            'writeFile' => [$this, 'writeFile'],
            'retrieveDocumentFromURL' => [$this, 'retrieveDocumentFromURL'],
            'executeShellCommand' => [$this, 'executeShellCommand'], // Add this line
        ];

        $name = $toolCall->function->name;
        if (!isset($functionMap[$name])) {
            $this->utils->debugPrint($debug, "Tool {$name} not found in function map.");
            return [
                'message' => [
                    'role' => 'tool',
                    'content' => "Error: Tool {$name} not found.",
                    'tool_call_id' => $toolCall->id,
                ],
                'contextVariables' => [],
                'agent' => null,
            ];
        }

        $args = json_decode($toolCall->function->arguments, true);
        $this->utils->debugPrint($debug, "Processing tool call: {$name} with arguments", $args);

        $func = $functionMap[$name];
        $rawResult = $func(...$args);
        $result = $this->handleFunctionResult($rawResult, $debug);

        return [
            'message' => [
                'role' => 'tool',
                'content' => $result->value,
                'tool_call_id' => $toolCall->id,
            ],
            'contextVariables' => $result->contextVariables,
            'agent' => $result->agent,
        ];
    }

    /**
     * Handle the result of a function call.
     *
     * @param mixed $result The raw result of the function call
     * @param bool $debug Whether to print debug information
     * @return Result The processed result
     * @throws \TypeError If the result cannot be cast to a string
     */
    private function handleFunctionResult($result, bool $debug): Result
    {
        if ($result instanceof Result) {
            return $result;
        }

        if ($result instanceof Agent) {
            return new Result(
                json_encode(['assistant' => $result->name]),
                $result
            );
        }

        try {
            return new Result((string)$result);
        } catch (\Exception $e) {
            $errorMessage = "Failed to cast response to string: " . print_r($result, true) .
                            ". Make sure agent functions return a string or Result object. Error: " . $e->getMessage();
            $this->utils->debugPrint($debug, $errorMessage);
            throw new \TypeError($errorMessage);
        }
    }

    /**
     * List files in a directory, restricted to the project root.
     *
     * @param string $directoryPath Path to the directory
     * @return string JSON encoded list of files
     * @throws FileOperationException If the directory is not found or cannot be read
     */
    public function listFiles(string $directoryPath): string
    {
        $safePath = $this->sanitizePath($directoryPath);
        
        if (!is_dir($safePath)) {
            throw new FileOperationException("Directory not found: $directoryPath");
        }

        $files = Amp\File\listFiles($safePath);
        if ($files === false) {
            throw new FileOperationException("Unable to read directory: $directoryPath");
        }

        $files = array_diff($files, array('.', '..'));
        return json_encode($files);
    }

    /**
     * Read the contents of a file, restricted to the project root.
     *
     * @param string $filePath Path to the file
     * @return string Contents of the file
     * @throws FileOperationException If the file is not found or cannot be read
     */
    public function readFile(string $filePath): string
    {
        $safePath = $this->sanitizePath($filePath);
        
        if (!file_exists($safePath)) {
            throw new FileOperationException("File not found: $filePath");
        }

        $content = null;

        try {
            return Amp\File\read($safePath);
        }
        catch (Throwable $e) {
            throw new FileOperationException("Unable to read file: $filePath".' '. $e->getMessage());
        }
        return $content;
    }

    /**
     * Write content to a file, restricted to the project root.
     *
     * @param string $filePath Path to the file
     * @param string $content Content to write
     * @param bool $overwriteFile Whether to overwrite an existing file
     * @return void 
     * @throws FileOperationException If the file cannot be written
     */
    public function writeFile(string $filePath, string $content, bool $overwriteFile = false): void
    {
        $safePath = $this->sanitizePath($filePath);
        
        if (file_exists($safePath) && !$overwriteFile) {
            throw new FileOperationException("File already exists and overwrite is not allowed: $filePath");
        }

        try {
            Amp\File\write($safePath, $content);
        }
        catch (Throwable $e) {
            throw new FileOperationException("Unable to write file: $filePath".' '. $e->getMessage());
        }
    }

    /**
     * Retrieve a document from a URL and optionally save it, restricted to the project root.
     *
     * @param string $url URL of the document
     * @param string|null $savePath Path to save the document (optional)
     * @return string Content of the document or success message if saved
     * @throws NetworkException If the document cannot be retrieved
     * @throws FileOperationException If the document cannot be saved
     */
    public function retrieveDocumentFromURL(string $url, ?string $savePath = null): string
    {
        try {
            $response = $this->httpClient->get($url);
            $content = $response->getBody()->getContents();

            if ($savePath) {
                $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
                $allowedExtensions = explode(',', getenv('ALLOWED_EXTENSIONS') ?? 'txt,pdf,doc,docx,csv');
                if (!in_array($extension, $allowedExtensions)) {
                    throw new FileOperationException("File extension not allowed: $extension");
                }

                $safeSavePath = $this->sanitizePath($savePath);
                $filePath = $savePath . '/' . basename($url);
                if (file_put_contents($filePath, $content) === false) {
                    throw new FileOperationException("Unable to save file: $filePath");
                }

                return "File downloaded and saved successfully at: " . $filePath;
            }

            return $content;
        } catch (GuzzleException $e) {
            throw new NetworkException("Unable to retrieve document from URL: " . $e->getMessage());
        }
    }

    /**
     * Sanitizes a given path to ensure it's within the project root.
     *
     * @param string $path The path to sanitize
     * @return string The sanitized path
     */
    private function sanitizePath(string $path): string
    {
        $realPath = realpath($this->projectRoot . DIRECTORY_SEPARATOR . $path);
        
        if (!$realPath || strpos($realPath, $this->projectRoot) !== 0) {
            throw new FileOperationException("Attempt to access file outside project directory: $path");
        }
        
        return $realPath;
    }

    /**
     * Execute a shell command.
     *
     * This method executes a shell command and returns the output.
     * It includes safety checks to prevent execution of potentially dangerous commands.
     *
     * @param string $command The shell command to execute
     * @return string The output of the command
     * @throws CommandExecutionException If the command fails or is not allowed
     */
    public function executeShellCommand(string $command): string
    {
        // Check if shell command execution is enabled
        if (!$this->isShellCommandAllowed()) {
            throw new CommandExecutionException("Shell command execution is disabled");
        }

        // Validate the command
        if (!$this->isValidCommand($command)) {
            throw new CommandExecutionException("Invalid or disallowed command: $command");
        }

        try {
            $output = shell_exec($command);
            if ($output === null) {
                throw new CommandExecutionException("Command failed: $command");
            }
            return trim($output);
        } catch (Throwable $e) {
            throw new CommandExecutionException("Error executing command: " . $e->getMessage());
        }
    }

    /**
     * Check if shell command execution is allowed.
     *
     * @return bool True if shell command execution is allowed, false otherwise
     */
    private function isShellCommandAllowed(): bool
    {
        return getenv('ALLOW_SHELL_EXECUTION') === 'true';
    }

    /**
     * Validate if the given command is valid and allowed.
     *
     * @param string $command The command to validate
     * @return bool True if the command is valid and allowed, false otherwise
     */
    private function isValidCommand(string $command): bool
    {
        // Implement command validation logic here
        // For example, you might want to allow only specific commands
        $allowedCommands = explode(',', getenv('ALLOW_SHELL_COMMANDS'), PHP_INT_MAX);
        $commandName = explode(' ', $command)[0];
        return in_array($commandName, $allowedCommands);
    }
}
