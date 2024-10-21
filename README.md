# phpSwarm

phpSwarm is a PHP framework for building multi-agent systems using OpenAI's GPT models. It provides a simple interface for creating agents, managing conversations, and handling tool calls, with support for both synchronous and asynchronous operations.

## Requirements

- PHP 8.1+
- Composer

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

3. Copy the `.env.example` file to `.env` and update the values:
   ```
   cp .env.example .env
   ```

4. Update the `.env` file with your OpenAI API key and other configuration options.

## Usage

Here's a basic example of how to use phpSwarm:

```php
<?php

require 'vendor/autoload.php';

use phpSwarm\Swarm;
use phpSwarm\Types\Agent;
use phpSwarm\Types\OpenAIModels;

$swarm = new Swarm();

$agent = new Agent(
    name: "FileBot",
    model: OpenAIModels::GPT_4,
    instructions: "You are a helpful assistant that can work with files and URLs.",
    functions: [
        'listFiles',
        'readFile',
        'writeFile',
        'retrieveDocumentFromURL'
    ]
);

$response = $swarm->run(
    agent: $agent,
    messages: [
        ['role' => 'user', 'content' => "List the files in the current directory."]
    ],
    debug: true
);

foreach ($response->messages as $message) {
    echo "{$message['role']}: {$message['content']}\n";
}
```

### Asynchronous Usage

phpSwarm supports asynchronous operations using amphp/Parallel. Here's how to use it asynchronously:

```php
<?php

require 'vendor/autoload.php';

use Amp\Future;
use phpSwarm\Swarm;
use phpSwarm\Types\Agent;

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

$future = $swarm->runAsync(
    agent: $agent,
    messages: [
        ['role' => 'user', 'content' => "Read the contents of 'example.txt'."]
    ],
    debug: true
);

$response = $future->await();

foreach ($response->messages as $message) {
    echo "{$message['role']}: {$message['content']}\n";
}
```

## Features

- Easy-to-use interface for creating and managing agents
- Support for OpenAI's chat completions API
- Streaming support for real-time responses
- Tool calling capabilities for extending agent functionality
- Debug mode for detailed logging
- Asynchronous operations support using amphp/parallel

## Development

### Running Tests

To run the test suite, use the following command:

```
composer test
```

### Static Analysis

This project uses Psalm for static analysis. To run Psalm, use:

```
./vendor/bin/psalm
```

### Code Styling

To fix code style issues, run:

```
./vendor/bin/php-cs-fixer fix
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the MIT License. See the `LICENSE` file for details.

## Acknowledgements

- OpenAI for providing the GPT models and API
- The PHP community for their excellent tools and libraries
