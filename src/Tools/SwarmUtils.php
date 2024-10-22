<?php

declare(strict_types=1);

namespace phpSwarm;

/**
 * SwarmUtils class provides utility functions for the phpSwarm library.
 */
class SwarmUtils
{
    /**
    * Returns the root directory of the current script.
    *
    * This method uses the `dirname()` function to retrieve the parent directory
    * of the current script. The `$levels` parameter allows specifying how many
    * levels up the directory tree to go.
    *
    * @param int $levels The number of levels up the directory tree to go. Default is 1.
    * @return string The path to the specified directory level.
    */
    public static function root(int $levels = 1)
    {
        // Change the second parameter to suit your needs
        return dirname(__FILE__, $levels);
    }

    /**
     * Prints debug information if debug mode is enabled.
     *
     * @param bool $debug Whether debug mode is enabled
     * @param string $message The message to print
     * @param mixed $data Additional data to print (optional)
     * @return void
     */
    public function debugPrint(bool $debug, string $message, $data = null): void
    {
        if (!$debug) {
            return;
        }
        $timestamp = date('Y-m-d H:i:s');
        $dataString = $data ? json_encode($data, JSON_PRETTY_PRINT) : '';
        echo "[$timestamp] $message $dataString\n";
    }

    /**
     * Converts a PHP function to a JSON representation compatible with OpenAI's function calling format.
     *
     * @param callable $function The function to convert
     * @return array The JSON representation of the function
     */
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

    /**
     * Converts a PHP type to a string representation compatible with OpenAI's function calling format.
     *
     * @param \ReflectionType $type The type to convert
     * @return string The string representation of the type
     */
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
