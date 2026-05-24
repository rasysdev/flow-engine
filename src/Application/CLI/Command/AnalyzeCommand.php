<?php

namespace FlowEngine\Application\CLI\Command;

use FlowEngine\Bootstrap\Container;
use FlowEngine\Console\ConsoleIO;

final class AnalyzeCommand implements Command
{
    public function __construct(
        private ConsoleIO $io
    ) {
    }

    public function supports(string $command): bool
    {
        return $command === 'analyze';
    }

    public function handle(array $argv): void
    {
        $projectPath = $argv[2] ?? null;

        if (!$projectPath) {
            $this->io->error('Usage: flow analyze <project_path>');
            return;
        }

        $container = new Container($projectPath);
        $container->analyzeProject()->execute();

        $this->io->json([
            'status' => 'ok',
            'message' => 'Project analyzed successfully',
        ]);
    }
}
