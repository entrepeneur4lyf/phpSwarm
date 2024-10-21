<?php

declare(strict_types=1);

namespace phpSwarm\Types;

/**
 * Class Response
 * 
 * Represents a response from the Swarm system, containing messages, agent information, and context variables.
 */
class Response
{
    /**
     * Response constructor.
     *
     * @param array $messages An array of messages in the response.
     * @param Agent|null $agent The agent associated with the response, if any.
     * @param array $contextVariables An array of context variables.
     */
    public function __construct(
        public array $messages = [],
        public ?Agent $agent = null,
        public array $contextVariables = []
    ) {}
}
