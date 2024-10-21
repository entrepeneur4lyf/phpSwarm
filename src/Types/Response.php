<?php

declare(strict_types=1);

namespace phpSwarm\Types;

class Response
{
    public function __construct(
        public array $messages = [],
        public ?Agent $agent = null,
        public array $contextVariables = []
    ) {}
}
