<?php

declare(strict_types=1);

namespace phpSwarm;

class SwarmUtils
{
    public function debugPrint(bool $debug, string $message, $data = null): void
    {
        if (!$debug) {
            return;
        }
        $timestamp = date('Y-m-d H:i:s');
        $dataString = $data ? json_encode($data, JSON_PRETTY_PRINT) : '';
        echo "[$timestamp] $message $dataString\n";
    }

    public function functionToJson($function): array
    {
        $reflection = new \ReflectionFunction($function);
        $parameters = [];
        $required = [];

        foreach ($reflection->getParameters() as $param) {
            $type = $param->getType();
            $parameters[$param->getName()] = [
                'type' => $type ? $this->getTypeString($type) : 'string'
            ];
            if (!$param->isOptional()) {
                $required[] = $param->getName();
            }
        }

        return [
            'name' => $reflection->getName(),
            'description' => $reflection->getDocComment() ?: '',
            'parameters' => [
                'type' => 'object',
                'properties' => $parameters,
                'required' => $required,
            ],
        ];
    }

    private function getTypeString(\ReflectionType $type): string
    {
        if ($type instanceof \ReflectionNamedType) {
            switch ($type->getName()) {
                case 'int':
                case 'float':
                    return 'number';
                case 'bool':
                    return 'boolean';
                case 'array':
                    return 'array';
                default:
                    return 'string';
            }
        }
        return 'string';
    }
}
