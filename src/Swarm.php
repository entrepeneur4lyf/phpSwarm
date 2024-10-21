<?php

declare(strict_types=1);

namespace phpSwarm;

use Amp\Deferred;
use Amp\Promise;
use OpenAI\Client;
use phpSwarm\Types\Agent;
use phpSwarm\Types\Response;

class Swarm
{
    private Client $client;
    private SwarmUtils $utils;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? \OpenAI::client(getenv('OPENAI_API_KEY'));
        $this->utils = new SwarmUtils();
    }

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

        while (count($history) - $initLen < $maxTurns && $activeAgent) {
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
    }

    public function runAsync(
        Agent $agent,
        array $messages,
        array $contextVariables = [],
        ?string $modelOverride = null,
        bool $stream = false,
        bool $debug = false,
        int $maxTurns = PHP_INT_MAX,
        bool $executeTools = true
    ): Promise {
        $deferred = new Deferred();

        \Amp\asyncCall(function () use ($deferred, $agent, $messages, $contextVariables, $modelOverride, $stream, $debug, $maxTurns, $executeTools) {
            try {
                $result = $this->run($agent, $messages, $contextVariables, $modelOverride, $stream, $debug, $maxTurns, $executeTools);
                $deferred->resolve($result);
            } catch (\Throwable $e) {
                $deferred->fail($e);
            }
        });

        return $deferred->promise();
    }

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

    private function prepareMessages(Agent $agent, array $history, array $contextVariables): array
    {
        $instructions = $agent->getInstructions($contextVariables);
        return [
            ['role' => 'system', 'content' => $instructions],
            ...$history
        ];
    }

    private function prepareFunctions(array $functions): array
    {
        return array_map(function ($function) {
            return [
                'type' => 'function',
                'function' => $this->utils->functionToJson($function)
            ];
        }, $functions);
    }

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

    private function handleToolCalls(array $toolCalls, Agent $agent, array $contextVariables, bool $debug): array
    {
        $swarmTools = new SwarmTools($this->utils);
        return $swarmTools->handleToolCalls($toolCalls, $agent, $contextVariables, $debug);
    }
}
