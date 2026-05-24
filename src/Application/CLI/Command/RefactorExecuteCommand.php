<?php

namespace FlowEngine\Application\CLI\Command;

use FlowEngine\Bootstrap\Container;
use FlowEngine\Console\ConsoleIO;
use FlowEngine\AI\Export\MarkdownFormatter;

final class RefactorExecuteCommand implements Command
{
    public function __construct(
        private ConsoleIO $io
    ) {
    }

    public function supports(string $command): bool
    {
        return $command === 'refactor-execute';
    }

    public function handle(array $argv): void
    {
        $projectPath = $argv[2] ?? null;
        $planLabel = $this->extractOption($argv, '--plan');
        $step = $this->extractOption($argv, '--step');
        $format = $this->extractOption($argv, '--format') ?? 'json';
        $markDone = in_array('--mark-done', $argv, true);

        if (!$projectPath || !$planLabel || $step === null) {
            $this->io->error(
                'Usage: flow refactor-execute <project_path> --plan=<label> --step=<N> [--format=json|markdown] [--mark-done]'
            );
            return;
        }

        $stepNumber = (int) $step;

        if ($stepNumber < 1) {
            $this->io->error('--step must be a positive integer.');
            return;
        }

        if (!in_array($format, ['json', 'markdown'], true)) {
            $this->io->error('Invalid format. Use --format=json or --format=markdown');
            return;
        }

        try {
            $container = new Container($projectPath);
            $container->analyzeProject()->execute();

            $guidance = $container->getRefactorGuidance()->execute($planLabel, $stepNumber);

            if ($markDone) {
                $container->recordRefactorStepCompletion()->execute($planLabel, $stepNumber, 'done');
                $this->io->info("Step {$stepNumber} marked as done in plan '{$planLabel}'.");
            }

            if ($format === 'markdown') {
                $formatter = new MarkdownFormatter();
                echo $formatter->formatRefactorGuidance($guidance);
            } else {
                $this->io->json($guidance->toArray());
            }
        } catch (\Throwable $e) {
            $this->io->error('Failed to get refactor guidance: ' . $e->getMessage());
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
