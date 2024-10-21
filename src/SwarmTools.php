<?php

declare(strict_types=1);

namespace phpSwarm;

use phpSwarm\Types\Agent;
use phpSwarm\Types\Result;
use phpSwarm\Types\OpenAIModels;
use phpSwarm\Exceptions\FileOperationException;
use phpSwarm\Exceptions\NetworkException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Class SwarmTools
 * 
 * This class provides various utility functions for file operations and network requests.
 */
class SwarmTools
{
    private SwarmUtils $utils;
    private Client $httpClient;

    /**
     * SwarmTools constructor.
     *
     * @param SwarmUtils $utils Utility class for Swarm operations
     */
    public function __construct(SwarmUtils $utils)
    {
        $this->utils = $utils;
        $this->httpClient = new Client();
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
     * List files in a directory.
     *
     * @param string $directoryPath Path to the directory
     * @return string JSON encoded list of files
     * @throws FileOperationException If the directory is not found or cannot be read
     */
    public function listFiles(string $directoryPath): string
    {
        if (!is_dir($directoryPath)) {
            throw new FileOperationException("Directory not found: $directoryPath");
        }

        $files = scandir($directoryPath);
        if ($files === false) {
            throw new FileOperationException("Unable to read directory: $directoryPath");
        }

        $files = array_diff($files, array('.', '..'));
        return json_encode($files);
    }

    /**
     * Read the contents of a file.
     *
     * @param string $filePath Path to the file
     * @return string Contents of the file
     * @throws FileOperationException If the file is not found or cannot be read
     */
    public function readFile(string $filePath): string
    {
        if (!file_exists($filePath)) {
            throw new FileOperationException("File not found: $filePath");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new FileOperationException("Unable to read file: $filePath");
        }

        return $content;
    }

    /**
     * Write content to a file.
     *
     * @param string $filePath Path to the file
     * @param string $content Content to write
     * @param bool $overwriteFile Whether to overwrite an existing file
     * @return string Success message
     * @throws FileOperationException If the file cannot be written
     */
    public function writeFile(string $filePath, string $content, bool $overwriteFile = false): string
    {
        if (file_exists($filePath) && !$overwriteFile) {
            throw new FileOperationException("File already exists and overwrite is not allowed: $filePath");
        }

        $result = file_put_contents($filePath, $content);
        if ($result === false) {
            throw new FileOperationException("Unable to write to file: $filePath");
        }

        return "File written successfully.";
    }

    /**
     * Retrieve a document from a URL and optionally save it.
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
                $allowedExtensions = explode(',', $_ENV['ALLOWED_EXTENSIONS'] ?? 'txt,pdf,doc,docx,csv');
                if (!in_array($extension, $allowedExtensions)) {
                    throw new FileOperationException("File extension not allowed: $extension");
                }

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
}
