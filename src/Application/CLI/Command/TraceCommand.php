<?php

namespace FlowEngine\Application\CLI\Command;

use FlowEngine\Bootstrap\Container;
use FlowEngine\Console\ConsoleIO;

final class TraceCommand implements Command
{
    public function __construct(
        private ConsoleIO $io
    ) {
    }

    public function supports(string $command): bool
    {
        return $command === 'trace';
    }

    public function handle(array $argv): void
    {
        $projectPath = $argv[2] ?? null;
        $nodeId = $this->extractOption($argv, '--node');
        $direction = $this->extractOption($argv, '--direction') ?? 'both';

        if (!$projectPath || !$nodeId) {
            $this->io->error('Usage: flow trace <project_path> --node=<Class::method> [--direction=downstream|upstream|both]');
            return;
        }

        $container = new Container($projectPath);
        $container->analyzeProject()->execute();

        $result = $container->traceFlow()->execute($nodeId, $direction);

        $this->io->json($result->toArray());
    }

    private function extractOption(array $argv, string $prefix): ?string
    {
        foreach ($argv as $arg) {
            if (str_starts_with($arg, $prefix . '=')) {
                return substr($arg, strlen($prefix) + 1);
            }
        }

        return null;
    }
}
