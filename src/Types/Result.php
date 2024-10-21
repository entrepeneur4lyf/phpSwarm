<?php

declare(strict_types=1);

namespace phpSwarm\Types;

class Result
{
    public function __construct(
        public string $value = "",
        public ?Agent $agent = null,
        public array $contextVariables = []
    ) {}
}
