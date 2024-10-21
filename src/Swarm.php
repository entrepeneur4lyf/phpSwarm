<?php

declare(strict_types=1);

namespace phpSwarm;

require __DIR__.'/../vendor/autoload.php';

use Amp\Future;
use Amp\Parallel\Worker;
use OpenAI\Client;
use phpSwarm\Types\Agent;
use phpSwarm\Types\Response;
use phpSwarm\Types\OpenAIModels;
use phpSwarm\Exceptions\FileOperationException;
use phpSwarm\Exceptions\NetworkException;

class Swarm
{
    private Client $client;
    private string $apikey;
    private SwarmUtils $utils;
    private Worker\Pool $pool;
    private SwarmTools $swarmTools;

    /**
     * Swarm constructor.
     *
     * @param string|null $apikey The OpenAI API key. If null, it will be fetched from the environment.
     * @param Worker\Pool|null $pool The worker pool to use. If null, a default pool will be created.
     */
    public function __construct(string $apikey = null, ?Worker\Pool $pool = null)
    {
        $this->apikey = $apikey ?? getenv('OPENAI_API_KEY');
        $this->client = \OpenAI::client($this->apikey);
        $this->utils = new SwarmUtils();
        $this->pool = $pool ?? Worker\pool();
        $this->swarmTools = new SwarmTools($this->utils);
    }

    /**
     * Run a conversation with an agent.
     *
     * @param Agent $agent The agent to run the conversation with.
     * @param array $messages The initial messages for the conversation.
     * @param array $contextVariables Additional context variables for the conversation.
     * @param string|null $modelOverride Override the default model for this conversation.
     * @param bool $stream Whether to stream the response.
     * @param bool $debug Whether to print debug information.
     * @param int $maxTurns The maximum number of turns in the conversation.
     * @param bool $executeTools Whether to execute tool calls.
     * @return Response The response from the conversation.
     * @throws NetworkException If there's an error communicating with the OpenAI API.
     * @throws FileOperationException If there's an error with file operations during tool execution.
     */
    public function run(
        Agent $agent,
        array $messages,
        array $contextVariables = [],
        ?string $modelOverride = null,
        bool $stream = false,
        bool $debug = false,
        int $maxTurns = PHP_INT_MAX,
        bool $executeTools = true
    ): Response {
        $activeAgent = $agent;
        $history = $messages;
        $initLen = count($messages);

        try {
            while (count($history) - $initLen < $maxTurns) {
                $response = $this->getChatCompletion(
                    $activeAgent,
                    $history,
                    $contextVariables,
                    $modelOverride,
                    $stream,
                    $debug
                );

                if ($stream) {
                    $message = $this->handleStreamedResponse($response, $activeAgent, $debug);
                } else {
                    $message = $this->handleResponse($response, $activeAgent, $debug);
                }

                $history[] = $message;

                if (empty($message['tool_calls']) || !$executeTools) {
                    $this->utils->debugPrint($debug, "Ending conversation.");
                    break;
                }

                $toolResults = $this->handleToolCalls(
                    $message['tool_calls'],
                    $activeAgent,
                    $contextVariables,
                    $debug
                );

                $history = array_merge($history, $toolResults['messages']);
                $contextVariables = array_merge($contextVariables, $toolResults['contextVariables']);
                if ($toolResults['agent']) {
                    $activeAgent = $toolResults['agent'];
                }
            }

            return new Response(
                array_slice($history, $initLen),
                $activeAgent,
                $contextVariables
            );
        } catch (\Exception $e) {
            if ($e instanceof NetworkException || $e instanceof FileOperationException) {
                throw $e;
            }
            throw new \RuntimeException("An error occurred during the conversation: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Run a conversation with an agent asynchronously.
     *
     * @param Agent $agent The agent to run the conversation with.
     * @param array $messages The initial messages for the conversation.
     * @param array $contextVariables Additional context variables for the conversation.
     * @param string|null $modelOverride Override the default model for this conversation.
     * @param bool $stream Whether to stream the response.
     * @param bool $debug Whether to print debug information.
     * @param int $maxTurns The maximum number of turns in the conversation.
     * @param bool $executeTools Whether to execute tool calls.
     * @return Future<Response> A future that resolves with the response from the conversation.
     */
    public function runAsync(
        Agent $agent,
        array $messages,
        array $contextVariables = [],
        ?string $modelOverride = null,
        bool $stream = false,
        bool $debug = false,
        int $maxTurns = PHP_INT_MAX,
        bool $executeTools = true
    ): Future {
        return $this->pool->submit(new class(
            $this,
            $agent,
            $messages,
            $contextVariables,
            $modelOverride,
            $stream,
            $debug,
            $maxTurns,
            $executeTools
        ) implements Worker\Task {
            public function __construct(
                private Swarm $swarm,
                private Agent $agent,
                private array $messages,
                private array $contextVariables,
                private ?string $modelOverride,
                private bool $stream,
                private bool $debug,
                private int $maxTurns,
                private bool $executeTools
            ) {}

            public function run(): Response
            {
                return $this->swarm->run(
                    $this->agent,
                    $this->messages,
                    $this->contextVariables,
                    $this->modelOverride,
                    $this->stream,
                    $this->debug,
                    $this->maxTurns,
                    $this->executeTools
                );
            }
        });
    }

    /**
     * Get a chat completion from the OpenAI API.
     *
     * @param Agent $agent The agent to use for the completion.
     * @param array $history The conversation history.
     * @param array $contextVariables Additional context variables.
     * @param string|null $modelOverride Override the default model.
     * @param bool $stream Whether to stream the response.
     * @param bool $debug Whether to print debug information.
     * @return mixed The response from the OpenAI API.
     */
    private function getChatCompletion(
        Agent $agent,
        array $history,
        array $contextVariables,
        ?string $modelOverride,
        bool $stream,
        bool $debug
    ) {
        $messages = $this->prepareMessages($agent, $history, $contextVariables);
        $this->utils->debugPrint($debug, "Getting chat completion for:", $messages);

        $params = [
            'model' => $modelOverride ?? $agent->model,
            'messages' => $messages,
            'stream' => $stream,
        ];

        if (!empty($agent->functions)) {
            $params['tools'] = $this->prepareFunctions($agent->functions);
        }

        return $this->client->chat()->create($params);
    }

    /**
     * Prepare messages for the OpenAI API.
     *
     * @param Agent $agent The agent to use for the messages.
     * @param array $history The conversation history.
     * @param array $contextVariables Additional context variables.
     * @return array The prepared messages.
     */
    private function prepareMessages(Agent $agent, array $history, array $contextVariables): array
    {
        $instructions = $agent->getInstructions($contextVariables);
        return [
            ['role' => 'system', 'content' => $instructions],
            ...$history
        ];
    }

    /**
     * Prepare functions for the OpenAI API.
     *
     * @param array $functions The functions to prepare.
     * @return array The prepared functions.
     */
    private function prepareFunctions(array $functions): array
    {
        return array_map(function ($function) {
            return [
                'type' => 'function',
                'function' => $this->utils->functionToJson($function)
            ];
        }, $functions);
    }

    /**
     * Handle a response from the OpenAI API.
     *
     * @param mixed $response The response from the API.
     * @param Agent $agent The agent used for the response.
     * @param bool $debug Whether to print debug information.
     * @return array The processed response.
     */
    private function handleResponse($response, Agent $agent, bool $debug): array
    {
        $message = $response->choices[0]->message;
        $this->utils->debugPrint($debug, "Received completion:", $message);
        
        return [
            'role' => $message->role,
            'content' => $message->content,
            'tool_calls' => $message->toolCalls ?? [],
            'sender' => $agent->name,
        ];
    }

    /**
     * Handle a streamed response from the OpenAI API.
     *
     * @param mixed $stream The streamed response from the API.
     * @param Agent $agent The agent used for the response.
     * @param bool $debug Whether to print debug information.
     * @return array The processed response.
     */
    private function handleStreamedResponse($stream, Agent $agent, bool $debug): array
    {
        $message = [
            'role' => 'assistant',
            'content' => '',
            'tool_calls' => [],
            'sender' => $agent->name,
        ];

        foreach ($stream as $response) {
            $delta = $response->choices[0]->delta;
            if (isset($delta->content)) {
                $message['content'] .= $delta->content;
            }
            if (isset($delta->toolCalls)) {
                foreach ($delta->toolCalls as $toolCall) {
                    $message['tool_calls'][] = $toolCall;
                }
            }
            $this->utils->debugPrint($debug, "Received stream chunk:", $delta);
        }

        return $message;
    }

    /**
     * Handle tool calls from the OpenAI API.
     *
     * @param array $toolCalls The tool calls to handle.
     * @param Agent $agent The agent used for the tool calls.
     * @param array $contextVariables Additional context variables.
     * @param bool $debug Whether to print debug information.
     * @return array The results of the tool calls.
     */
    private function handleToolCalls(array $toolCalls, Agent $agent, array $contextVariables, bool $debug): array
    {
        if ($agent->parallelToolCalls && count($toolCalls) > 1) {
            return $this->handleParallelToolCalls($toolCalls, $agent, $contextVariables, $debug);
        }
        return $this->swarmTools->handleToolCalls($toolCalls, $agent, $contextVariables, $debug);
    }

    private function handleParallelToolCalls(array $toolCalls, Agent $agent, array $contextVariables, bool $debug): array
    {
        $futures = [];
        foreach ($toolCalls as $toolCall) {
            $futures[] = $this->pool->submit(new class($this->swarmTools, $toolCall, $agent, $contextVariables, $debug) implements Worker\Task {
                public function __construct(
                    private SwarmTools $swarmTools,
                    private array $toolCall,
                    private Agent $agent,
                    private array $contextVariables,
                    private bool $debug
                ) {}

                public function run()
                {
                    return $this->swarmTools->handleSingleToolCall($this->toolCall, $this->agent, $this->contextVariables, $this->debug);
                }
            });
        }

        $results = Future\await($futures);
        
        $messages = [];
        $mergedContextVariables = $contextVariables;
        $newAgent = null;

        foreach ($results as $result) {
            $messages = array_merge($messages, $result['messages']);
            $mergedContextVariables = array_merge($mergedContextVariables, $result['contextVariables']);
            if ($result['agent']) {
                $newAgent = $result['agent'];
            }
        }

        return [
            'messages' => $messages,
            'contextVariables' => $mergedContextVariables,
            'agent' => $newAgent,
        ];
    }
}
