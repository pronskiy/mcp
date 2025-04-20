<?php

namespace Pronskiy\Mcp;

class McpServerException extends \Exception
{
    /**
     * Create a new exception for an invalid tool handler result
     */
    public static function invalidToolResult(mixed $result): self
    {
        $type = is_object($result) ? get_class($result) : gettype($result);
        return new self("Invalid tool handler result: expected string or CallToolResult, got {$type}");
    }

    /**
     * Create a new exception for an invalid prompt handler result
     */
    public static function invalidPromptResult(mixed $result): self
    {
        $type = is_object($result) ? get_class($result) : gettype($result);
        return new self("Invalid prompt handler result: expected string, array, or GetPromptResult, got {$type}");
    }

    /**
     * Create a new exception for an invalid resource handler result
     */
    public static function invalidResourceResult(mixed $result): self
    {
        $type = is_object($result) ? get_class($result) : gettype($result);
        return new self("Invalid resource handler result: expected string, SplFileObject, resource, or ReadResourceResult, got {$type}");
    }

    /**
     * Create a new exception for an unknown tool
     */
    public static function unknownTool(string $name): self
    {
        return new self("Unknown tool: {$name}");
    }

    /**
     * Create a new exception for an unknown prompt
     */
    public static function unknownPrompt(string $name): self
    {
        return new self("Unknown prompt: {$name}");
    }

    /**
     * Create a new exception for an unknown resource
     */
    public static function unknownResource(string $uri): self
    {
        return new self("Unknown resource: {$uri}");
    }
}
