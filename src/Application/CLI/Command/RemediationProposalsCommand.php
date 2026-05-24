<?php

namespace FlowEngine\Application\CLI\Command;

use FlowEngine\Application\DTO\RemediationProposalReportDTO;
use FlowEngine\Bootstrap\Container;
use FlowEngine\Console\ConsoleIO;

final class RemediationProposalsCommand implements Command
{
    public function __construct(
        private ConsoleIO $io
    ) {
    }

    public function supports(string $command): bool
    {
        return $command === 'remediation-proposals';
    }

    public function handle(array $argv): void
    {
        $projectPath = $argv[2] ?? null;
        $format = $this->extractOption($argv, '--format') ?? 'json';
        $max = (int) ($this->extractOption($argv, '--max') ?? '10');
        $saveLabel = $this->extractOption($argv, '--save');

        if (!$projectPath) {
            $this->io->error('Usage: flow remediation-proposals <project_path> [--max=<N>] [--format=json|markdown] [--save=<label>]');
            return;
        }

        if (!in_array($format, ['json', 'markdown'], true)) {
            $this->io->error('Invalid format. Use --format=json or --format=markdown');
            return;
        }

        if ($max <= 0) {
            $this->io->error('Invalid max value. Use --max=<N> with N >= 1');
            return;
        }

        try {
            $container = new Container($projectPath);
            $container->analyzeProject()->execute();
            $report = $container->generateRemediationProposals()->execute($max);

            if ($saveLabel !== null) {
                $container->saveRemediationProposals()->execute($saveLabel, $report);
                $this->io->info("Remediation proposals saved as '{$saveLabel}'");
            }

            if ($format === 'markdown') {
                echo $this->formatMarkdown($report);
                return;
            }

            $this->io->json($report->toArray());
        } catch (\Throwable $e) {
            $this->io->error('Failed to generate remediation proposals: ' . $e->getMessage());
        }
    }

    private function formatMarkdown(RemediationProposalReportDTO $report): string
    {
        $lines = [];
        $lines[] = '# Remediation Proposals';
        $lines[] = '';
        $lines[] = '| Metric | Value |';
        $lines[] = '|---|---|';
        $lines[] = "| Generated at | {$report->generatedAt} |";
        $lines[] = "| Total proposals | {$report->total} |";
        $lines[] = '';
        $lines[] = 'All proposals require explicit human approval before execution.';
        $lines[] = '';

        foreach ($report->proposals as $proposal) {
            $lines[] = "## [{$proposal->priority}] {$proposal->title}";
            $lines[] = '';
            $lines[] = "- id: `{$proposal->id}`";
            $lines[] = "- category: `{$proposal->category}`";
            $lines[] = "- target: `{$proposal->target}`";
            $lines[] = "- reason: {$proposal->reason}";
            $lines[] = '- actions:';
            foreach ($proposal->actions as $action) {
                $lines[] = "  - {$action}";
            }
            $lines[] = '';
        }

        return implode("\n", $lines) . "\n";
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
