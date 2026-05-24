<?php

namespace FlowEngine\Application\CLI\Command;

use FlowEngine\AI\Export\MarkdownFormatter;
use FlowEngine\Bootstrap\Container;
use FlowEngine\Console\ConsoleIO;

final class ArchitectureCommand implements Command
{
    public function __construct(
        private ConsoleIO $io
    ) {
    }

    public function supports(string $command): bool
    {
        return $command === 'architecture';
    }

    public function handle(array $argv): void
    {
        $projectPath = $argv[2] ?? null;

        if (!$projectPath) {
            $this->io->error('Usage: flow architecture <project_path> [--context]');
            return;
        }

        $container = new Container($projectPath);
        $container->analyzeProject()->execute();

        $result = $container->analyzeArchitecture()->execute();

        if (in_array('--context', $argv, true)) {
            echo (new MarkdownFormatter())->formatArchitecture($result);
            return;
        }

        $this->io->json($result->toArray());
    }
}
