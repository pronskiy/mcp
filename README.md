# 🐉 The fast, PHP way to build MCP servers

The Model Context Protocol (MCP) is a new, standardized way to provide context and tools to your LLMs, and `pronskiy/mcp` makes building MCP servers simple and intuitive. 

Create tools, expose resources, define prompts, and connect components with clean PHP code.

## Installation

With composer:

```bash
composer require pronskiy/mcp
```

## Usage

```php
require 'vendor/autoload.php';

$server = new \Pronskiy\Mcp\Server('simple-mcp-server');

$server
    ->tool('add-numbers', 'Adds two numbers together', function(float $num1, float $num2) {
        return "The sum of {$num1} and {$num2} is " . ($num1 + $num2);
    })
    ->tool('multiply-numbers', 'Multiplies two numbers', function(float $num1, float $num2) {
        return "The product of {$num1} and {$num2} is " . ($num1 * $num2);
    })
;

$server->run();
```

## Credits

- https://github.com/logiscape/mcp-sdk-php

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
