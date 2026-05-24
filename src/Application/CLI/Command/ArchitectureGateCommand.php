<?php

namespace FlowEngine\Application\CLI\Command;

use FlowEngine\Application\DTO\PredictViolationsDTO;
use FlowEngine\Bootstrap\Container;
use FlowEngine\Console\ConsoleIO;

/**
 * CI-friendly architecture gate.
 *
 * Exits with code 0 when the gate passes, code 1 when it fails.
 * Use `--fail-on` to tune sensitivity; use `--baseline` for incremental enforcement
 * on legacy projects that already carry known violations.
 *
 * Usage:
 *   flow architecture-gate <project_path> [--baseline=<label>] [--fail-on=any|new|critical] [--format=json|markdown]
 *
 * Examples:
 *   # Fail on any violation (greenfield default)
 *   flow architecture-gate .
 *
 *   # Fail only on new violations introduced since the saved snapshot
 *   flow architecture-gate . --baseline=before-pr --fail-on=new
 *
 *   # Fail only on CRITICAL violations regardless of baseline
 *   flow architecture-gate . --fail-on=critical
 */
final class ArchitectureGateCommand implements Command
{
    private const VALID_FAIL_ON = ['any', 'new', 'critical'];

    public function __construct(
        private ConsoleIO $io
    ) {}

    public function supports(string $command): bool
    {
        return $command === 'architecture-gate';
    }

    public function handle(array $argv): void
    {
        $projectPath   = $argv[2] ?? null;
        $baselineLabel = $this->extractOption($argv, '--baseline');
        $failOn        = $this->extractOption($argv, '--fail-on') ?? 'any';
        $format        = $this->extractOption($argv, '--format') ?? 'json';

        if (!$projectPath) {
            $this->io->error('Usage: flow architecture-gate <project_path> [--baseline=<label>] [--fail-on=any|new|critical] [--format=json|markdown]');
            exit(1);
        }

        if (!in_array($failOn, self::VALID_FAIL_ON, true)) {
            $this->io->error('Invalid --fail-on value. Use: any, new, critical');
            exit(1);
        }

        if (!in_array($format, ['json', 'markdown'], true)) {
            $this->io->error('Invalid --format value. Use: json, markdown');
            exit(1);
        }

        try {
            $container = new Container($projectPath);
            $container->analyzeProject()->execute();

            $dto = $container->predictViolations()->execute($failOn, $baselineLabel);

            if ($format === 'markdown') {
                echo $this->formatMarkdown($dto);
            } else {
                $this->io->json($dto->toArray());
            }

            if ($dto->shouldFail) {
                exit(1);
            }
        } catch (\Throwable $e) {
            $this->io->error('Architecture gate failed: ' . $e->getMessage());
            exit(1);
        }
    }

    private function formatMarkdown(PredictViolationsDTO $dto): string
    {
        $status = $dto->shouldFail ? '❌ GATE FAILED' : '✅ GATE PASSED';
        $md     = "# Architecture Gate — {$status}\n\n";

        $md .= "| Metric | Value |\n|---|---|\n";
        $md .= "| Policy (--fail-on) | `{$dto->failOn}` |\n";
        $md .= "| Baseline | " . ($dto->baselineLabel ?? '*(none)*') . " |\n";
        $md .= "| Current violations | {$dto->totalCurrent} |\n";

        if ($dto->hasBaseline) {
            $md .= "| New violations | {$dto->totalNew} |\n";
            $md .= "| Resolved violations | {$dto->totalResolved} |\n";
        }

        $md .= "\n";

        if ($dto->isClean) {
            $md .= "No architecture violations detected.\n\n";
            return $md;
        }

        if ($dto->hasBaseline && $dto->newViolations !== []) {
            $md .= "## New violations\n\n";
            foreach ($dto->newViolations as $v) {
                $md .= $this->formatViolation($v);
            }
        }

        if ($dto->hasBaseline && $dto->resolvedViolations !== []) {
            $md .= "## Resolved violations\n\n";
            foreach ($dto->resolvedViolations as $v) {
                $md .= $this->formatViolation($v);
            }
        }

        if ($dto->currentViolations !== []) {
            $md .= "## All current violations\n\n";
            foreach ($dto->currentViolations as $v) {
                $md .= $this->formatViolation($v);
            }
        }

        return $md;
    }

    private function formatViolation(array $v): string
    {
        $severity = $v['severity']  ?? 'UNKNOWN';
        $from     = $v['from']      ?? '?';
        $to       = $v['to']        ?? '?';
        $reason   = $v['reason']    ?? '';
        return "- **[{$severity}]** `{$from}` → `{$to}`" . ($reason !== '' ? ": {$reason}" : '') . "\n";
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
