<?php

namespace FlowEngine\Application\CLI\Command;

use FlowEngine\Bootstrap\Container;
use FlowEngine\Console\ConsoleIO;

final class RemediationStatusCommand implements Command
{
    public function __construct(
        private ConsoleIO $io
    ) {
    }

    public function supports(string $command): bool
    {
        return $command === 'remediation-status';
    }

    public function handle(array $argv): void
    {
        $projectPath = $argv[2] ?? null;
        $planLabel = $this->extractOption($argv, '--plan');
        $format = $this->extractOption($argv, '--format') ?? 'json';

        if (!$projectPath || !$planLabel) {
            $this->io->error('Usage: flow remediation-status <project_path> --plan=<label> [--format=json|markdown]');
            return;
        }

        if (!in_array($format, ['json', 'markdown'], true)) {
            $this->io->error('Invalid format. Use --format=json or --format=markdown');
            return;
        }

        try {
            $container = new Container($projectPath);
            $status = $container->getRemediationProposalStatus()->execute($planLabel);

            if ($format === 'markdown') {
                echo $this->toMarkdown($status);
                return;
            }

            $this->io->json($status);
        } catch (\Throwable $e) {
            $this->io->error('Failed to retrieve remediation status: ' . $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $status
     */
    private function toMarkdown(array $status): string
    {
        $lines = [];
        $lines[] = '# Remediation Proposal Status';
        $lines[] = '';
        $lines[] = '| Metric | Value |';
        $lines[] = '|---|---|';
        $lines[] = "| Plan label | `{$status['planLabel']}` |";
        $lines[] = "| Generated at | {$status['generatedAt']} |";
        $lines[] = "| Total | {$status['total']} |";
        $lines[] = "| Approved | {$status['approvedCount']} |";
        $lines[] = "| Pending | {$status['pendingCount']} |";
        $lines[] = '';
        $lines[] = '| ID | Priority | Category | Approved | Title |';
        $lines[] = '|---|---|---|---|---|';

        foreach (($status['proposals'] ?? []) as $proposal) {
            if (!is_array($proposal)) {
                continue;
            }

            $approved = ($proposal['approved'] ?? false) ? 'yes' : 'no';
            $lines[] = sprintf(
                '| `%s` | %s | %s | %s | %s |',
                (string) ($proposal['id'] ?? ''),
                (string) ($proposal['priority'] ?? 'P3'),
                (string) ($proposal['category'] ?? 'unknown'),
                $approved,
                (string) ($proposal['title'] ?? '')
            );
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

