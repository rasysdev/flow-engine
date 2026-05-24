<?php

namespace FlowEngine\Application\CLI\Command;

use FlowEngine\Application\MCP\McpServer;
use FlowEngine\Console\ConsoleIO;

final class McpCommand implements Command
{
    public function __construct(
        private ConsoleIO $io
    ) {
    }

    public function supports(string $command): bool
    {
        return $command === 'mcp';
    }

    public function handle(array $argv): void
    {
        // McpServer owns stdio exclusively; avoid any startup banner so MCP
        // clients never receive non-protocol bytes on startup.
        (new McpServer())->run();
    }
}
