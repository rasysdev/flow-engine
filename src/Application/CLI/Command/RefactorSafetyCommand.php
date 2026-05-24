<?php

namespace FlowEngine\Application\CLI\Command;

use FlowEngine\Bootstrap\Container;
use FlowEngine\Console\ConsoleIO;

final class RefactorSafetyCommand implements Command
{
    public function __construct(
        private ConsoleIO $io
    ) {
    }

    public function supports(string $command): bool
    {
        return $command === 'refactor-safety';
    }

    public function handle(array $argv): void
    {
        $projectPath = $argv[2] ?? null;
        $nodeId = $this->extractOption($argv, '--node');

        if (!$projectPath || !$nodeId) {
            $this->io->error('Usage: flow refactor-safety <project_path> --node=<Class::method>');
            return;
        }

        $container = new Container($projectPath);
        $container->analyzeProject()->execute();

        $result = $container->assessRefactorSafety()->execute($nodeId);

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
