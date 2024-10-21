<?php

declare(strict_types=1);

namespace phpSwarm\Tests;

use PHPUnit\Framework\TestCase;
use phpSwarm\Swarm;
use phpSwarm\SwarmTools;
use phpSwarm\SwarmUtils;
use phpSwarm\Types\Agent;
use OpenAI\Client;
use OpenAI\Responses\Chat\CreateResponse;
use OpenAI\Responses\Chat\CreateResponseChoice;
use OpenAI\Responses\Chat\CreateResponseMessage;
use Amp\Success;

class SwarmTest extends TestCase
{
    private SwarmTools $swarmTools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->swarmTools = new SwarmTools(new SwarmUtils());
    }

    public function testRunWithSimpleConversation()
    {
        // Mock the OpenAI client
        $mockClient = $this->createMock(Client::class);
        $mockChatCompletion = $this->createMock(\OpenAI\Contracts\Resources\ChatContract::class);

        // Set up the expected response
        $expectedResponse = new CreateResponse([
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'gpt-3.5-turbo-0613',
            'choices' => [
                new CreateResponseChoice([
                    'index' => 0,
                    'message' => new CreateResponseMessage([
                        'role' => 'assistant',
                        'content' => 'Hello! How can I assist you today?'
                    ]),
                    'finish_reason' => 'stop'
                ])
            ],
            'usage' => [
                'prompt_tokens' => 9,
                'completion_tokens' => 12,
                'total_tokens' => 21
            ]
        ]);

        // Set up expectations
        $mockChatCompletion->expects($this->once())
            ->method('create')
            ->willReturn($expectedResponse);

        $mockClient->expects($this->once())
            ->method('chat')
            ->willReturn($mockChatCompletion);

        // Create a Swarm instance with the mocked client
        $swarm = new Swarm($mockClient);

        // Create an agent
        $agent = new Agent(
            name: "TestBot",
            model: "gpt-3.5-turbo",
            instructions: "You are a helpful assistant."
        );

        // Run a conversation
        $response = $swarm->run(
            agent: $agent,
            messages: [
                ['role' => 'user', 'content' => "Hello!"]
            ]
        );

        // Assert the response
        $this->assertCount(1, $response->messages);
        $this->assertEquals('assistant', $response->messages[0]['role']);
        $this->assertEquals('Hello! How can I assist you today?', $response->messages[0]['content']);
    }

    public function testRunAsyncWithSimpleConversation()
    {
        // Mock the OpenAI client
        $mockClient = $this->createMock(Client::class);
        $mockChatCompletion = $this->createMock(\OpenAI\Contracts\Resources\ChatContract::class);

        // Set up the expected response
        $expectedResponse = new CreateResponse([
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'gpt-3.5-turbo-0613',
            'choices' => [
                new CreateResponseChoice([
                    'index' => 0,
                    'message' => new CreateResponseMessage([
                        'role' => 'assistant',
                        'content' => 'Hello! How can I assist you today?'
                    ]),
                    'finish_reason' => 'stop'
                ])
            ],
            'usage' => [
                'prompt_tokens' => 9,
                'completion_tokens' => 12,
                'total_tokens' => 21
            ]
        ]);

        // Set up expectations
        $mockChatCompletion->expects($this->once())
            ->method('create')
            ->willReturn($expectedResponse);

        $mockClient->expects($this->once())
            ->method('chat')
            ->willReturn($mockChatCompletion);

        // Create a Swarm instance with the mocked client
        $swarm = new Swarm($mockClient);

        // Create an agent
        $agent = new Agent(
            name: "TestBot",
            model: "gpt-3.5-turbo",
            instructions: "You are a helpful assistant."
        );

        // Run an async conversation
        $promise = $swarm->runAsync(
            agent: $agent,
            messages: [
                ['role' => 'user', 'content' => "Hello!"]
            ]
        );

        // Assert that the promise resolves to the expected response
        $promise->onResolve(function ($error, $response) {
            $this->assertNull($error);
            $this->assertCount(1, $response->messages);
            $this->assertEquals('assistant', $response->messages[0]['role']);
            $this->assertEquals('Hello! How can I assist you today?', $response->messages[0]['content']);
        });

        // Run the event loop to process the promise
        \Amp\Loop::run();
    }
}
