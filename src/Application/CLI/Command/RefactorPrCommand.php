<?php

namespace FlowEngine\Application\CLI\Command;

use FlowEngine\Application\DTO\GeneratePullRequestDTO;
use FlowEngine\Bootstrap\Container;
use FlowEngine\Console\ConsoleIO;

final class RefactorPrCommand implements Command
{
    public function __construct(
        private ConsoleIO $io
    ) {}

    public function supports(string $command): bool
    {
        return $command === 'refactor-pr';
    }

    public function handle(array $argv): void
    {
        $projectPath = $argv[2] ?? null;
        $planLabel   = $this->extractOption($argv, '--plan');
        $format      = $this->extractOption($argv, '--format') ?? 'markdown';
        $publish     = in_array('--publish', $argv, true);

        if (!$projectPath || !$planLabel) {
            $this->io->error('Usage: flow refactor-pr <project_path> --plan=<label> [--format=json|markdown] [--publish]');
            return;
        }

        if (!in_array($format, ['json', 'markdown'], true)) {
            $this->io->error('Invalid format. Use --format=json or --format=markdown');
            return;
        }

        try {
            $container = new Container($projectPath);
            $dto       = $container->generatePullRequest()->execute($planLabel);

            if ($format === 'json') {
                $this->io->json($dto->toArray());
            } else {
                $this->io->info("Branch: {$dto->branch}");
                $this->io->info("Title:  {$dto->title}");
                echo "\n" . $dto->body . "\n";
            }

            if ($publish) {
                $this->publishPr($dto);
            }
        } catch (\Throwable $e) {
            $this->io->error('Failed to generate PR: ' . $e->getMessage());
        }
    }

    /**
     * Publishes the PR via the `gh` CLI. Requires GitHub CLI to be installed
     * and authenticated. The `--head` branch must already exist on the remote.
     */
    private function publishPr(GeneratePullRequestDTO $dto): void
    {
        $this->io->info('Publishing PR via gh CLI...');

        $cmd = sprintf(
            'gh pr create --title=%s --body=%s --head=%s 2>&1',
            escapeshellarg($dto->title),
            escapeshellarg($dto->body),
            escapeshellarg($dto->branch),
        );

        $output   = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        if ($exitCode === 0) {
            $url = trim(implode("\n", $output));
            $this->io->info("PR created: {$url}");
        } else {
            $this->io->error("gh pr create failed:\n" . implode("\n", $output));
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
