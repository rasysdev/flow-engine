<?php

namespace FlowEngine\Application\CLI\Command;

use FlowEngine\Bootstrap\Container;
use FlowEngine\Console\ConsoleIO;
use FlowEngine\AI\Export\MarkdownFormatter;

final class RefactorPlanCommand implements Command
{
    public function __construct(
        private ConsoleIO $io
    ) {
    }

    public function supports(string $command): bool
    {
        return $command === 'refactor-plan';
    }

    public function handle(array $argv): void
    {
        $projectPath = $argv[2] ?? null;
        $nodeId = $this->extractOption($argv, '--node');
        $format = $this->extractOption($argv, '--format') ?? 'json';
        $saveLabel = $this->extractOption($argv, '--save');

        if (!$projectPath || !$nodeId) {
            $this->io->error('Usage: flow refactor-plan <project_path> --node=<Class::method> [--format=json|markdown] [--save=<label>]');
            return;
        }

        if (!in_array($format, ['json', 'markdown'], true)) {
            $this->io->error('Invalid format. Use --format=json or --format=markdown');
            return;
        }

        try {
            // Bootstrap container and analyze project
            $container = new Container($projectPath);
            $container->analyzeProject()->execute();

            // Generate refactor plan
            $plan = $container->generateRefactorPlan()->execute($nodeId);

            // Save plan if requested
            if ($saveLabel !== null) {
                $container->saveRefactorPlan()->execute($saveLabel, $plan);
                $this->io->info("Plan saved as '{$saveLabel}'");
            }

            // Output based on format
            if ($format === 'markdown') {
                $formatter = new MarkdownFormatter();
                $markdown = $formatter->formatRefactorPlan($plan);
                echo $markdown;
            } else {
                $this->io->json($plan->toArray());
            }
        } catch (\Throwable $e) {
            $this->io->error('Failed to generate refactor plan: ' . $e->getMessage());
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
