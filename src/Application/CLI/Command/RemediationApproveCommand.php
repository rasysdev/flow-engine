<?php

namespace FlowEngine\Application\CLI\Command;

use FlowEngine\Bootstrap\Container;
use FlowEngine\Console\ConsoleIO;

final class RemediationApproveCommand implements Command
{
    public function __construct(
        private ConsoleIO $io
    ) {
    }

    public function supports(string $command): bool
    {
        return $command === 'remediation-approve';
    }

    public function handle(array $argv): void
    {
        $projectPath = $argv[2] ?? null;
        $planLabel = $this->extractOption($argv, '--plan');
        $proposalId = $this->extractOption($argv, '--id');
        $approvedBy = $this->extractOption($argv, '--by');
        $format = $this->extractOption($argv, '--format') ?? 'json';

        if (!$projectPath || !$planLabel || !$proposalId) {
            $this->io->error(
                'Usage: flow remediation-approve <project_path> --plan=<label> --id=<proposal_id> [--by=<name>] [--format=json|markdown]'
            );
            return;
        }

        if (!in_array($format, ['json', 'markdown'], true)) {
            $this->io->error('Invalid format. Use --format=json or --format=markdown');
            return;
        }

        try {
            $container = new Container($projectPath);
            $result = $container->approveRemediationProposal()->execute($planLabel, $proposalId, $approvedBy);

            if ($format === 'markdown') {
                echo $this->toMarkdown($result);
                return;
            }

            $this->io->json($result);
        } catch (\Throwable $e) {
            $this->io->error('Failed to approve remediation proposal: ' . $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $result
     */
    private function toMarkdown(array $result): string
    {
        $lines = [];
        $lines[] = '# Remediation Approval';
        $lines[] = '';
        $lines[] = "| Plan | `{$result['planLabel']}` |";
        $lines[] = "| Proposal | `{$result['proposalId']}` |";
        $lines[] = "| Approved by | {$result['approvedBy']} |";
        $lines[] = "| Approved at | {$result['approvedAt']} |";
        $lines[] = "| Already approved | " . (($result['alreadyApproved'] ?? false) ? 'yes' : 'no') . " |";
        $lines[] = "| Approved/Total | {$result['approvedCount']}/{$result['total']} |";
        $lines[] = '';

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

