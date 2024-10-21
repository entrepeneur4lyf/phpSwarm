<?php

declare(strict_types=1);

namespace phpSwarm\Types;

class Agent
{
    private $instructions;

    public function __construct(
        public string $name = "Agent",
        public string $model = "gpt-3.5-turbo",
        $instructions = "You are a helpful assistant.",
        public array $functions = [],
        public ?string $toolChoice = null,
        public bool $parallelToolCalls = true
    ) {
        $this->setInstructions($instructions);
    }

    public function setInstructions(string|callable $instructions): void
    {
        if (!is_string($instructions) && !is_callable($instructions)) {
            throw new \InvalidArgumentException('Instructions must be a string or callable');
        }
        $this->instructions = $instructions;
    }

    public function getInstructions(array $contextVariables): string
    {
        if (is_callable($this->instructions)) {
            return ($this->instructions)($contextVariables);
        }
        return $this->instructions;
    }
}
