<?php

namespace FlowEngine\Application\CLI\Command;

use FlowEngine\Bootstrap\Container;
use FlowEngine\Console\ConsoleIO;

final class RefactorValidateCommand implements Command
{
    public function __construct(
        private ConsoleIO $io
    ) {
    }

    public function supports(string $command): bool
    {
        return $command === 'refactor-validate';
    }

    public function handle(array $argv): void
    {
        $projectPath = $argv[2] ?? null;
        $planLabel = $this->extractOption($argv, '--plan');
        $step = $this->extractOption($argv, '--step');

        if (!$projectPath || !$planLabel || $step === null) {
            $this->io->error(
                'Usage: flow refactor-validate <project_path> --plan=<label> --step=<N>'
            );
            return;
        }

        $stepNumber = (int) $step;

        if ($stepNumber < 1) {
            $this->io->error('--step must be a positive integer.');
            return;
        }

        try {
            $container = new Container($projectPath);
            $container->analyzeProject()->execute();

            $validation = $container->validateRefactorStep()->execute($planLabel, $stepNumber);

            $this->io->json($validation->toArray());

            if (!$validation->passed) {
                exit(1);
            }
        } catch (\Throwable $e) {
            $this->io->error('Failed to validate refactor step: ' . $e->getMessage());
            exit(1);
        }
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
