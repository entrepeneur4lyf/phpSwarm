# phpSwarm

phpSwarm is a PHP framework for building multi-agent systems using OpenAI's GPT models. It provides a simple interface for creating agents, managing conversations, and handling tool calls, with support for both synchronous and asynchronous operations.

## Requirements

- PHP 8.1 or higher
- Composer
- OpenAI API key

## Installation

1. Clone this repository:
   ```
   git clone https://github.com/your-username/phpswarm.git
   cd phpswarm
   ```

2. Install dependencies:
   ```
   composer install
   ```

3. Set up your OpenAI API key:
   ```
   export OPENAI_API_KEY=your_api_key_here
   ```

## Usage

### Synchronous Usage

Here's a basic example of how to use phpSwarm synchronously:

```php
<?php

require 'vendor/autoload.php';

use phpSwarm\Swarm;
use phpSwarm\Types\Agent;

// Initialize the Swarm client
$swarm = new Swarm();

// Create an agent
$agent = new Agent(
    name: "FileBot",
    model: "gpt-3.5-turbo",
    instructions: "You are a helpful assistant that can work with files and URLs.",
    functions: [
        'listFiles',
        'readFile',
        'writeFile',
        'retrieveDocumentFromURL'
    ]
);

// Run a conversation
$response = $swarm->run(
    agent: $agent,
    messages: [
        ['role' => 'user', 'content' => "List the files in the current directory."]
    ],
    debug: true
);

// Print the conversation
foreach ($response->messages as $message) {
    echo "{$message['role']}: {$message['content']}\n";
}
```

### Asynchronous Usage

phpSwarm also supports asynchronous operations using amphp. Here's how to use it asynchronously:

```php
<?php

require 'vendor/autoload.php';

use Amp\Loop;
use phpSwarm\Swarm;
use phpSwarm\Types\Agent;

Loop::run(function () {
    $swarm = new Swarm();

    $agent = new Agent(
        name: "FileBot",
        model: "gpt-3.5-turbo",
        instructions: "You are a helpful assistant that can work with files and URLs.",
        functions: [
            'listFiles',
            'readFile',
            'writeFile',
            'retrieveDocumentFromURL'
        ]
    );

    $promise = $swarm->runAsync(
        agent: $agent,
        messages: [
            ['role' => 'user', 'content' => "Read the contents of 'example.txt'."]
        ],
        debug: true
    );

    $response = yield $promise;

    foreach ($response->messages as $message) {
        echo "{$message['role']}: {$message['content']}\n";
    }
});
```

## Features

- Easy-to-use interface for creating and managing agents
- Support for OpenAI's chat completions API
- Streaming support for real-time responses
- Tool calling capabilities for extending agent functionality
- Debug mode for detailed logging
- Asynchronous operations support using amphp

### Built-in Tool Functions

phpSwarm comes with several built-in tool functions that agents can use:

1. `listFiles(string $directoryPath)`: Lists all files in the specified directory.
2. `readFile(string $filePath)`: Reads and returns the contents of the specified file.
3. `writeFile(string $filePath, string $content, bool $overwriteFile = false)`: Writes content to the specified file. If the file exists, it will only overwrite if $overwriteFile is true.
4. `retrieveDocumentFromURL(string $url)`: Retrieves and returns the content from the specified URL.

These functions can be used by specifying them in the `functions` array when creating an Agent.

## Running Tests

To run the test suite, use the following command:

```
composer test
```

This will run all the tests in the `tests` directory using PHPUnit.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the MIT License. See the `LICENSE` file for details.

## Acknowledgements

- OpenAI for providing the GPT models and API
- The PHP community for their excellent tools and libraries
- amphp for asynchronous programming support
