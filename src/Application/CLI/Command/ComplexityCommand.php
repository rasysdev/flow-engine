<?php

namespace FlowEngine\Application\CLI\Command;

use FlowEngine\AI\Export\MarkdownFormatter;
use FlowEngine\Bootstrap\Container;
use FlowEngine\Console\ConsoleIO;

final class ComplexityCommand implements Command
{
    public function __construct(
        private ConsoleIO $io
    ) {
    }

    public function supports(string $command): bool
    {
        return $command === 'complexity';
    }

    public function handle(array $argv): void
    {
        $projectPath = $argv[2] ?? null;

        if (!$projectPath) {
            $this->io->error('Usage: flow complexity <project_path> [--context]');
            return;
        }

        $container = new Container($projectPath);
        $container->analyzeProject()->execute();

        $result = $container->analyzeComplexity()->execute();

        if (in_array('--context', $argv, true)) {
            echo (new MarkdownFormatter())->formatComplexity($result);
            return;
        }

        $this->io->json($result->toArray());
    }
}
