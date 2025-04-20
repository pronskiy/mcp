<?php

declare(strict_types=1);

namespace Pronskiy\Mcp;

/**
 * Exception thrown by the McpServer class
 */

use Mcp\Server\Server as McpServer;
use Mcp\Server\ServerRunner;
use Mcp\Server\NotificationOptions;
use Mcp\Types\BlobResourceContents;
use Mcp\Types\CallToolResult;
use Mcp\Types\GetPromptResult;
use Mcp\Types\ListPromptsResult;
use Mcp\Types\ListResourcesResult;
use Mcp\Types\ListToolsResult;
use Mcp\Types\Prompt;
use Mcp\Types\PromptArgument;
use Mcp\Types\PromptMessage;
use Mcp\Types\ReadResourceResult;
use Mcp\Types\Resource;
use Mcp\Types\Role;
use Mcp\Types\TextContent;
use Mcp\Types\TextResourceContents;
use Mcp\Types\Tool;
use Mcp\Types\ToolInputProperties;
use Mcp\Types\ToolInputSchema;
use ReflectionNamedType;

/**
 * Simple MCP Server implementation
 *
 * This class provides a more elegant, fluent interface for creating MCP servers.
 * It simplifies the process of defining tools, prompts, and resources by using 
 * reflection and automatic type conversion.
 *
 * Features:
 * - Fluent interface with method chaining
 * - Automatic schema generation from callback parameters
 * - Simplified error handling with custom exceptions
 * - Automatic conversion of return values to appropriate MCP types
 * - Static facade-like interface for common operations
 * - Support for resources, tools, and prompts
 *
 * Example usage:
 *
 * ```php
 * $server = new McpServer('my-server');
 *
 * // Define a tool with automatic schema generation
 * $server->tool('add-numbers', 'Adds two numbers', function(float $a, float $b) {
 *     return "The sum is " . ($a + $b);
 * });
 *
 * // Define a prompt with automatic argument detection
 * $server->prompt('greeting', 'Greeting message', function(string $name) {
 *     return "Hello, {$name}!";
 * });
 *
 * // Define a resource
 * $server->resource(
 *     uri: 'file:///example.txt',
 *     name: 'Example File',
 *     callback: fn() => "File content"
 * );
 *
 * // Run the server
 * $server->run();
 * ```
 *
 * Or using the static facade:
 *
 * ```php
 * McpServer::defineTool('add-numbers', 'Adds two numbers', function(float $a, float $b) {
 *     return "The sum is " . ($a + $b);
 * });
 *
 * McpServer::start();
 * ```
 */
class Server
{
    /**
     * The singleton instance of the server
     */
    protected static ?self $instance = null;

    /**
     * Get the singleton instance of the server
     *
     * This method returns the singleton instance of the server, creating it if it doesn't exist.
     * It's used by the static facade methods to access the underlying server instance.
     *
     * Example:
     * ```php
     * // Get the default instance
     * $server = McpServer::getInstance();
     *
     * // Get an instance with a custom name
     * $server = McpServer::getInstance('custom-server');
     * ```
     *
     * @param string $name The name of the server
     * @return self The singleton instance of the server
     */
    public static function getInstance(string $name = 'mcp-server'): self
    {
        if (self::$instance === null) {
            self::$instance = new self($name);
        }

        return self::$instance;
    }

    /**
     * Define a tool using the static facade
     *
     * This static method provides a Laravel-style facade for defining tools.
     * It delegates to the tool method of the singleton instance.
     *
     * Example:
     * ```php
     * McpServer::defineTool('add-numbers', 'Adds two numbers', function(float $a, float $b) {
     *     return "The sum is " . ($a + $b);
     * });
     * ```
     *
     * @param string $name The name of the tool
     * @param string $description A description of what the tool does
     * @param callable $callback The function that implements the tool
     * @return self For method chaining
     * @throws McpServerException If the callback returns an invalid result
     * @see McpServer::tool()
     */
    public static function addTool(string $name, string $description, callable $callback): self
    {
        return self::getInstance()->tool($name, $description, $callback);
    }

    /**
     * Define a prompt using the static facade
     *
     * This static method provides a Laravel-style facade for defining prompts.
     * It delegates to the prompt method of the singleton instance.
     *
     * Example:
     * ```php
     * McpServer::definePrompt('greeting', 'A greeting message', function(string $name) {
     *     return "Hello, {$name}!";
     * });
     * ```
     *
     * @param string $name The name of the prompt
     * @param string $description A description of what the prompt does
     * @param callable $callback The function that implements the prompt
     * @return self For method chaining
     * @throws McpServerException If the callback returns an invalid result
     * @see McpServer::prompt()
     */
    public static function addPrompt(string $name, string $description, callable $callback): self
    {
        return self::getInstance()->prompt($name, $description, $callback);
    }

    /**
     * Define a resource using the static facade
     *
     * This static method provides a Laravel-style facade for defining resources.
     * It delegates to the resource method of the singleton instance.
     *
     * Example:
     * ```php
     * McpServer::defineResource(
     *     uri: 'file:///example.txt',
     *     name: 'Example Text File',
     *     mimeType: 'text/plain',
     *     callback: function() {
     *         return "This is the content of the file";
     *     }
     * );
     * ```
     *
     * @param string $uri The URI of the resource
     * @param string $name The name of the resource
     * @param string $description The description of the resource
     * @param string $mimeType The MIME type of the resource
     * @param callable $callback The callback that returns the resource content
     * @return self For method chaining
     * @throws McpServerException If the callback returns an invalid result
     * @see McpServer::resource()
     */
    public static function addResource(string $uri, string $name, string $description = '', string $mimeType = 'text/plain', ?callable $callback = null): self
    {
        return self::getInstance()->resource($uri, $name, $description, $mimeType, $callback);
    }

    /**
     * Run the server using the static facade
     *
     * This static method provides a Laravel-style facade for running the server.
     * It delegates to the run method of the singleton instance.
     *
     * Example:
     * ```php
     * // Define tools, prompts, and resources...
     *
     * // Start the server with default notification options
     * McpServer::start();
     *
     * // Or with custom notification options
     * McpServer::start(
     *     resourcesChanged: true,
     *     toolsChanged: true,
     *     promptsChanged: false
     * );
     * ```
     *
     * @param bool $resourcesChanged Whether to notify clients when resources change
     * @param bool $toolsChanged Whether to notify clients when tools change
     * @param bool $promptsChanged Whether to notify clients when prompts change
     * @throws \Throwable If an error occurs while running the server
     * @see McpServer::run()
     */
    public static function start(bool $resourcesChanged = true, bool $toolsChanged = true, bool $promptsChanged = true): void
    {
        self::getInstance()->run($resourcesChanged, $toolsChanged, $promptsChanged);
    }
    
    /**
     * The underlying MCP Server instance
     */
    protected McpServer $server;

    /**
     * Registered tools
     */
    protected array $tools = [];

    /**
     * Registered prompts
     */
    protected array $prompts = [];

    /**
     * Registered resources
     */
    protected array $resources = [];

    /**
     * Registered resource handlers
     */
    protected array $resourceHandlers = [];

    /**
     * Create a new MCP Server instance
     */
    public function __construct(string $name)
    {
        $this->server = new McpServer($name);

        // Register default handlers
        $this->registerDefaultHandlers();
    }

    /**
     * Register the default handlers for tools and prompts
     */
    protected function registerDefaultHandlers(): void
    {
        // Register tools/list handler
        $this->server->registerHandler('tools/list', function() {
            $toolObjects = array_values($this->tools);
            return new ListToolsResult($toolObjects);
        });

        // Register tools/call handler
        $this->server->registerHandler('tools/call', function($params) {
            $name = $params->name;
            $arguments = $params->arguments ?? new \stdClass();

            if (!isset($this->toolHandlers[$name])) {
                throw McpServerException::unknownTool($name);
            }

            $handler = $this->toolHandlers[$name];

            try {
                return $handler($arguments);
            } catch (\Throwable $e) {
                return new CallToolResult(
                    content: [new TextContent(
                        text: "Error: " . $e->getMessage()
                    )],
                    isError: true
                );
            }
        });

        // Register prompts/list handler
        $this->server->registerHandler('prompts/list', function() {
            $promptObjects = array_values($this->prompts);
            return new ListPromptsResult($promptObjects);
        });

        // Register prompts/get handler
        $this->server->registerHandler('prompts/get', function($params) {
            $name = $params->name;
            $arguments = $params->arguments ?? new \stdClass();

            if (!isset($this->promptHandlers[$name])) {
                throw McpServerException::unknownPrompt($name);
            }

            $handler = $this->promptHandlers[$name];
            return $handler($arguments);
        });

        // Register resources/list handler
        $this->server->registerHandler('resources/list', function() {
            $resourceObjects = array_values($this->resources);
            return new ListResourcesResult($resourceObjects);
        });

        // Register resources/read handler
        $this->server->registerHandler('resources/read', function($params) {
            $uri = $params->uri;

            if (!isset($this->resourceHandlers[$uri])) {
                throw McpServerException::unknownResource($uri);
            }

            $handler = $this->resourceHandlers[$uri];
            return $handler();
        });
    }

    /**
     * Registered tool handlers
     */
    protected array $toolHandlers = [];

    /**
     * Registered prompt handlers
     */
    protected array $promptHandlers = [];

    /**
     * Define a new tool
     *
     * This method creates a new tool with the given name and description, and
     * automatically generates the input schema from the callback's parameters.
     * The callback can return either a string or a CallToolResult object.
     *
     * Example:
     * ```php
     * $server->tool('add-numbers', 'Adds two numbers', function(float $num1, float $num2) {
     *     return "The sum is " . ($num1 + $num2);
     * });
     * ```
     *
     * @param string $name The name of the tool
     * @param string $description A description of what the tool does
     * @param callable $callback The function that implements the tool
     * @return self For method chaining
     */
    public function tool(string $name, string $description, callable $callback): self
    {
        // Extract input schema from callback parameters
        $schema = $this->buildSchemaFromCallback($callback);

        // Create the tool
        $tool = new Tool(
            name: $name,
            inputSchema: $schema,
            description: $description
        );

        // Store the tool and handler
        $this->tools[] = $tool;
        $this->toolHandlers[$name] = function($args) use ($callback) {
            // Convert stdClass to array for easier handling
            $arguments = json_decode(json_encode($args), true);

            // Call the handler
            $result = $callback(...array_values($arguments));

            // If the result is already a CallToolResult, return it
            if ($result instanceof CallToolResult) {
                return $result;
            }

            // Otherwise, wrap it in a CallToolResult
            return new CallToolResult(
                content: [new TextContent(
                    text: (string) $result
                )]
            );
        };

        return $this;
    }

    /**
     * Build a schema from a callback's parameters
     */
    protected function buildSchemaFromCallback(callable $callback): ToolInputSchema
    {
        $reflection = new \ReflectionFunction($callback);
        $parameters = $reflection->getParameters();

        $properties = [];
        $required = [];

        foreach ($parameters as $param) {
            $name = $param->getName();
            $type = $param->getType();
            $typeName = $type instanceof ReflectionNamedType ? $type->getName() : 'string';

            // Map PHP types to JSON Schema types
            $jsonType = match($typeName) {
                'int', 'float' => 'number',
                'bool' => 'boolean',
                'array' => 'array',
                'object', 'stdClass' => 'object',
                default => 'string'
            };

            $properties[$name] = [
                'type' => $jsonType,
                'description' => "Parameter: {$name}"
            ];

            if (!$param->isOptional()) {
                $required[] = $name;
            }
        }

        return new ToolInputSchema(
            properties: ToolInputProperties::fromArray($properties),
            required: $required
        );
    }

    /**
     * Define a new prompt
     *
     * This method creates a new prompt with the given name and description, and
     * automatically generates the arguments from the callback's parameters.
     * The callback can return a string, an array of strings, or a GetPromptResult object.
     *
     * Example:
     * ```php
     * $server->prompt('greeting', 'A greeting message', function(string $name, string $language = 'English') {
     *     return "Hello, {$name}!";
     * });
     * ```
     *
     * @param string $name The name of the prompt
     * @param string $description A description of what the prompt does
     * @param callable $callback The function that implements the prompt
     * @return self For method chaining
     * @throws McpServerException If the callback returns an invalid result
     */
    public function prompt(string $name, string $description, callable $callback): self
    {
        // Extract arguments from callback parameters
        $arguments = $this->buildArgumentsFromCallback($callback);

        // Create the prompt
        $prompt = new Prompt(
            name: $name,
            description: $description,
            arguments: $arguments
        );

        // Store the prompt and handler
        $this->prompts[] = $prompt;
        $this->promptHandlers[$name] = function($args) use ($callback) {
            // Convert stdClass to array for easier handling
            $arguments = json_decode(json_encode($args), true);

            // Call the handler
            $result = $callback(...array_values($arguments));

            // If the result is already a GetPromptResult, return it
            if ($result instanceof GetPromptResult) {
                return $result;
            }

            // If the result is a string, wrap it in a GetPromptResult
            if (is_string($result)) {
                return new GetPromptResult(
                    messages: [
                        new PromptMessage(
                            role: Role::USER,
                            content: new TextContent(
                                text: $result
                            )
                        )
                    ]
                );
            }

            // If the result is an array of strings, wrap each in a PromptMessage
            if (is_array($result)) {
                $messages = [];
                foreach ($result as $message) {
                    $messages[] = new PromptMessage(
                        role: Role::USER,
                        content: new TextContent(
                            text: (string) $message
                        )
                    );
                }
                return new GetPromptResult(messages: $messages);
            }

            throw McpServerException::invalidPromptResult($result);
        };

        return $this;
    }

    /**
     * Build arguments from a callback's parameters
     */
    protected function buildArgumentsFromCallback(callable $callback): array
    {
        $reflection = new \ReflectionFunction($callback);
        $parameters = $reflection->getParameters();

        $arguments = [];

        foreach ($parameters as $param) {
            $arguments[] = new PromptArgument(
                name: $param->getName(),
                description: "Parameter: {$param->getName()}",
                required: !$param->isOptional()
            );
        }

        return $arguments;
    }

    /**
     * Define a new resource
     *
     * This method creates a new resource with the given URI, name, description, and MIME type.
     * The callback should return the content of the resource, which can be a string,
     * an SplFileObject, a resource, or a ReadResourceResult object.
     *
     * Example:
     * ```php
     * // Simple text resource
     * $server->resource(
     *     uri: 'file:///example.txt',
     *     name: 'Example Text File',
     *     mimeType: 'text/plain',
     *     callback: function() {
     *         return "This is the content of the file";
     *     }
     * );
     *
     * // Resource from an actual file
     * $server->resource(
     *     uri: 'file:///readme.md',
     *     name: 'README',
     *     mimeType: 'text/markdown',
     *     callback: function() {
     *         return file_get_contents('README.md');
     *     }
     * );
     * ```
     *
     * @param string $uri The URI of the resource
     * @param string $name The name of the resource
     * @param string $description The description of the resource
     * @param string $mimeType The MIME type of the resource
     * @param callable $callback The callback that returns the resource content
     * @return self For method chaining
     * @throws McpServerException If the callback returns an invalid result
     */
    public function resource(string $uri, string $name, string $description = '', string $mimeType = 'text/plain', ?callable $callback = null): self
    {
        // Create the resource
        $resource = new Resource(
            name: $name,
            uri: $uri,
            description: $description,
            mimeType: $mimeType
        );

        // Store the resource and handler
        $this->resources[] = $resource;
        $this->resourceHandlers[$uri] = function() use ($callback, $uri, $mimeType) {
            // Call the handler
            $result = $callback();

            // If the result is already a ReadResourceResult, return it
            if ($result instanceof ReadResourceResult) {
                return $result;
            }

            // If the result is a string, wrap it in a ReadResourceResult
            if (is_string($result)) {
                return new ReadResourceResult(
                    contents: [
                        new TextResourceContents(
                            text: $result,
                            uri: $uri,
                            mimeType: $mimeType
                        )
                    ]
                );
            }

            // If the result is binary data, wrap it in a ReadResourceResult
            if ($result instanceof \SplFileObject || is_resource($result)) {
                // Read the file or resource into a string
                $content = '';
                if ($result instanceof \SplFileObject) {
                    $content = $result->fread($result->getSize());
                } else {
                    $content = stream_get_contents($result);
                }

                return new ReadResourceResult(
                    contents: [
                        new BlobResourceContents(
                            blob: base64_encode($content),
                            uri: $uri,
                            mimeType: $mimeType
                        )
                    ]
                );
            }

            throw McpServerException::invalidResourceResult($result);
        };

        return $this;
    }

    /**
     * Run the server
     *
     * This method starts the MCP server and begins processing requests.
     * It will block until the server is terminated.
     *
     * Example:
     * ```php
     * // Run with default notification options
     * $server->run();
     *
     * // Run with custom notification options
     * $server->run(
     *     resourcesChanged: true,
     *     toolsChanged: true,
     *     promptsChanged: false
     * );
     * ```
     *
     * @param bool $resourcesChanged Whether to notify clients when resources change
     * @param bool $toolsChanged Whether to notify clients when tools change
     * @param bool $promptsChanged Whether to notify clients when prompts change
     * @throws \Throwable If an error occurs while running the server
     */
    public function run(bool $resourcesChanged = true, bool $toolsChanged = true, bool $promptsChanged = true): void
    {
        $notificationOptions = new NotificationOptions(
            promptsChanged: $promptsChanged,
            resourcesChanged: $resourcesChanged,
            toolsChanged: $toolsChanged
        );

        $initOptions = $this->server->createInitializationOptions($notificationOptions);
        $runner = new ServerRunner($this->server, $initOptions);

        try {
            $runner->run();
        } catch (\Throwable $e) {
            echo "An error occurred: " . $e->getMessage() . "\n";
        }
    }
}
