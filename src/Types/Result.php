<?php

declare(strict_types=1);

namespace phpSwarm\Types;

/**
 * Class Result
 * 
 * Represents the result of an operation in the Swarm system.
 * It contains a value, an optional agent, and context variables.
 */
class Result
{
    /**
     * Result constructor.
     *
     * @param string $value The resulting value of the operation.
     * @param Agent|null $agent An optional agent associated with the result.
     * @param array $contextVariables An array of context variables related to the result.
     */
    public function __construct(
        public string $value = "",
        public ?Agent $agent = null,
        public array $contextVariables = []
    ) {}
}
