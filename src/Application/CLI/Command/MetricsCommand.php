<?php

namespace FlowEngine\Application\CLI\Command;

use FlowEngine\AI\Export\MarkdownFormatter;
use FlowEngine\Bootstrap\Container;
use FlowEngine\Console\ConsoleIO;

final class MetricsCommand implements Command
{
    public function __construct(
        private ConsoleIO $io
    ) {
    }

    public function supports(string $command): bool
    {
        return $command === 'metrics';
    }

    public function handle(array $argv): void
    {
        $projectPath = $argv[2] ?? null;

        if (!$projectPath) {
            $this->io->error('Usage: flow metrics <project_path> [--context]');
            return;
        }

        $container = new Container($projectPath);
        $container->analyzeProject()->execute();

        $result = $container->analyzeMetrics()->execute();

        if (in_array('--context', $argv, true)) {
            echo (new MarkdownFormatter())->formatMetrics($result);
            return;
        }

        $this->io->json($result->toArray());
    }
}
