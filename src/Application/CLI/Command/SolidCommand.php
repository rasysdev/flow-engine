<?php

namespace FlowEngine\Application\CLI\Command;

use FlowEngine\Application\DTO\SolidReportDTO;
use FlowEngine\Bootstrap\Container;
use FlowEngine\Console\ConsoleIO;

/**
 * CLI command: flow solid <project_path> [--format=text|json] [--principle=srp|isp|dip|ocp]
 *
 * Detecta violações dos princípios SOLID no projeto analisado.
 *
 * Exemplos:
 *   flow solid .
 *   flow solid . --format=json
 *   flow solid . --principle=dip
 */
final class SolidCommand implements Command
{
    private const VALID_PRINCIPLES = ['srp', 'isp', 'dip', 'ocp', 'all'];

    public function __construct(
        private readonly ConsoleIO $io
    ) {
    }

    public function supports(string $command): bool
    {
        return $command === 'solid';
    }

    public function handle(array $argv): void
    {
        $projectPath = $argv[2] ?? null;

        if (!$projectPath) {
            $this->io->error('Usage: flow solid <project_path> [--format=text|json] [--principle=srp|isp|dip|ocp|all]');
            return;
        }

        $format    = $this->extractOption($argv, '--format') ?? 'text';
        $principle = strtolower($this->extractOption($argv, '--principle') ?? 'all');

        if (!in_array($principle, self::VALID_PRINCIPLES, true)) {
            $this->io->error('Invalid --principle. Use: srp, isp, dip, ocp, all');
            return;
        }

        $container = new Container($projectPath);
        $container->analyzeProject()->execute();

        $report = $container->analyzeSolid()->execute();

        if ($format === 'json') {
            $data = $principle === 'all'
                ? $report->toArray()
                : ['totalIssues' => count($report->{$principle}), $principle => $report->{$principle}];

            echo json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            return;
        }

        $this->printText($report, $principle);
    }

    private function printText(SolidReportDTO $report, string $principle): void
    {
        echo "\nSOLID Analysis Report\n";
        echo str_repeat('=', 60) . "\n";
        echo "Total issues: {$report->totalIssues}\n\n";

        if ($report->totalIssues === 0) {
            echo "No SOLID violations detected.\n\n";
            return;
        }

        echo "Summary:\n";
        foreach ($report->summary as $p => $count) {
            $indicator = $count > 0 ? '⚠' : '✓';
            echo "  {$indicator} [{$p}] {$count} issue(s)\n";
        }
        echo "\n";

        if (($principle === 'all' || $principle === 'srp') && $report->srp !== []) {
            echo "[S] Single Responsibility — " . count($report->srp) . " issue(s)\n";
            echo str_repeat('-', 60) . "\n";
            foreach ($report->srp as $v) {
                $sev = str_pad($v['severity'], 6);
                echo "  [{$sev}] {$v['class']}\n";
                echo "          Fan-out: {$v['fanOut']} classes\n";
                echo "          {$v['reason']}\n\n";
            }
        }

        if (($principle === 'all' || $principle === 'isp') && $report->isp !== []) {
            echo "[I] Interface Segregation — " . count($report->isp) . " issue(s)\n";
            echo str_repeat('-', 60) . "\n";
            foreach ($report->isp as $v) {
                $sev = str_pad($v['severity'], 6);
                echo "  [{$sev}] {$v['interface']}\n";
                echo "          Methods: {$v['methodCount']} (" . implode(', ', $v['methods']) . ")\n";
                echo "          {$v['reason']}\n\n";
            }
        }

        if (($principle === 'all' || $principle === 'dip') && $report->dip !== []) {
            echo "[D] Dependency Inversion — " . count($report->dip) . " issue(s)\n";
            echo str_repeat('-', 60) . "\n";
            foreach ($report->dip as $v) {
                $sev = str_pad($v['severity'], 6);
                echo "  [{$sev}] {$v['from']}\n";
                echo "          → new {$v['to']}\n";
                echo "          {$v['reason']}\n\n";
            }
        }

        if (($principle === 'all' || $principle === 'ocp') && $report->ocp !== []) {
            echo "[O] Open/Closed Opportunities — " . count($report->ocp) . " suggestion(s)\n";
            echo str_repeat('-', 60) . "\n";
            foreach ($report->ocp as $v) {
                echo "  [LOW]   {$v['class']}\n";
                echo "          Instantiates: {$v['directInstantiations']} concrete classes\n";
                echo "          {$v['reason']}\n\n";
            }
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
