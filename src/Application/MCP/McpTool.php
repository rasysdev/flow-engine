<?php

namespace FlowEngine\Application\MCP;

/**
 * @api
 */
final readonly class McpTool
{
    public function __construct(
        public string $name,
        public string $description,
        public array  $inputSchema,
        /** @var callable(array): string */
        public mixed  $handler,
    ) {}
}
