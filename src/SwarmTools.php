<?php

declare(strict_types=1);

namespace phpSwarm;

use phpSwarm\Types\Agent;
use phpSwarm\Types\Result;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class SwarmTools
{
    private SwarmUtils $utils;
    private array $config;
    private Client $httpClient;

    public function __construct(SwarmUtils $utils)
    {
        $this->utils = $utils;
        $this->config = require __DIR__ . '/config.php';
        $this->httpClient = new Client();
    }

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

    public function listFiles(string $directoryPath): string
    {
        if (!is_dir($directoryPath)) {
            return "Error: Directory not found.";
        }

        $files = scandir($directoryPath);
        $files = array_diff($files, array('.', '..'));
        return json_encode($files);
    }

    public function readFile(string $filePath): string
    {
        if (!file_exists($filePath)) {
            return "Error: File not found.";
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return "Error: Unable to read file.";
        }

        return $content;
    }

    public function writeFile(string $filePath, string $content, bool $overwriteFile = false): string
    {
        if (file_exists($filePath) && !$overwriteFile) {
            return "Error: File already exists and overwrite is not allowed.";
        }

        $result = file_put_contents($filePath, $content);
        if ($result === false) {
            return "Error: Unable to write to file.";
        }

        return "File written successfully.";
    }

    public function retrieveDocumentFromURL(string $url, ?string $savePath = null): string
    {
        try {
            $response = $this->httpClient->get($url);
            $content = $response->getBody()->getContents();

            if ($savePath) {
                $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
                if (!in_array($extension, $this->config['allowed_extensions'])) {
                    return "Error: File extension not allowed.";
                }

                $filePath = $savePath . '/' . basename($url);
                if (file_put_contents($filePath, $content) === false) {
                    return "Error: Unable to save file.";
                }

                return "File downloaded and saved successfully at: " . $filePath;
            }

            return $content;
        } catch (GuzzleException $e) {
            return "Error: Unable to retrieve document from URL. " . $e->getMessage();
        }
    }
}
