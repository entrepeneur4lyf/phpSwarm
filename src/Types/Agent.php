<?php

declare(strict_types=1);

namespace phpSwarm\Types;

use phpSwarm\Types\OpenAIModels;

/**
 * Class Agent
 *
 * Represents an agent in the Swarm system, with properties such as name, model, instructions, and functions.
 */
class Agent
{
    /**
     * @var string|callable The instructions for the agent.
     */
    private $instructions;

    /**
     * Agent constructor.
     *
     * @param string $name The name of the agent.
     * @param string $model The model used by the agent, default is OpenAIModels::GPT4OMINI.
     * @param string|callable $instructions The instructions for the agent.
     * @param array $functions An array of functions available to the agent.
     * @param string|null $toolChoice The tool choice for the agent.
     * @param bool $parallelToolCalls Whether the agent can make parallel tool calls.
     */
    public function __construct(
        public string $name = "Agent",
        public string $model = OpenAIModels::GPT4OMINI,
        $instructions = "You are a helpful assistant.",
        public array $functions = [],
        public ?string $toolChoice = null,
        public bool $parallelToolCalls = true
    ) {
        $this->setInstructions($instructions);
    }

    /**
     * Set the instructions for the agent.
     *
     * @param string|callable $instructions The instructions to set.
     * @throws \InvalidArgumentException If the instructions are not a string or callable.
     */
    public function setInstructions(string|callable $instructions): void
    {
        if (!is_string($instructions) && !is_callable($instructions)) {
            throw new \InvalidArgumentException('Instructions must be a string or callable');
        }
        $this->instructions = $instructions;
    }

    /**
     * Get the instructions for the agent.
     *
     * @param array $contextVariables An array of context variables.
     * @return string The instructions for the agent.
     */
    public function getInstructions(array $contextVariables): string
    {
        if (is_callable($this->instructions)) {
            return ($this->instructions)($contextVariables);
        }
        return $this->instructions;
    }
}
