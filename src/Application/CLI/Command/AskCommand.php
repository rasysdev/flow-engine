<?php

namespace FlowEngine\Application\CLI\Command;

use FlowEngine\AI\Export\ExportOptions;
use FlowEngine\Bootstrap\Container;
use FlowEngine\Console\ConsoleIO;

final class AskCommand implements Command
{
    public function __construct(
        private ConsoleIO $io
    ) {
    }

    public function supports(string $command): bool
    {
        return $command === 'ask';
    }

    public function handle(array $argv): void
    {
        $question = $argv[2] ?? null;
        $projectPath = $argv[3] ?? null;

        if (!$question || !$projectPath) {
            $this->io->error('Usage: flow ask "<question>" <project_path> [--minimal]');
            return;
        }

        $minimal = in_array('--minimal', $argv, true);
        $options = $minimal ? ExportOptions::minimal() : ExportOptions::all();

        $container = new Container($projectPath);
        $container->analyzeProject()->execute();

        $result = $container->askQuestion()->execute($question, $options);

        $this->io->json($result->toArray());
    }
}
