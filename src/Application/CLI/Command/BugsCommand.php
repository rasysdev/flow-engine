<?php

namespace FlowEngine\Application\CLI\Command;

use FlowEngine\Application\DTO\BugReportDTO;
use FlowEngine\Bootstrap\Container;
use FlowEngine\Console\ConsoleIO;

/**
 * CLI command: flow bugs <project_path> [--min-score=N] [--type=TYPE] [--format=text|json]
 */
final class BugsCommand implements Command
{
    public function __construct(
        private ConsoleIO $io
    ) {}

    public function supports(string $command): bool
    {
        return $command === 'bugs';
    }

    public function handle(array $argv): void
    {
        $projectPath = $argv[2] ?? null;

        if (!$projectPath) {
            $this->io->error('Usage: flow bugs <project_path> [--min-score=N] [--type=TYPE] [--format=text|json]');
            return;
        }

        $minScore = (int) ($this->extractOption($argv, '--min-score') ?? '0');
        $type     = $this->extractOption($argv, '--type') ?? '';
        $format   = $this->extractOption($argv, '--format') ?? 'text';

        $container = new Container($projectPath);
        $container->analyzeProject()->execute();

        $report = $container->analyzeBugs()->execute($minScore, $type);

        if ($format === 'json') {
            echo $report->toJson();
            return;
        }

        $this->printText($report);
    }

    private function printText(BugReportDTO $report): void
    {
        echo "Bug Detection Report\n";
        echo str_repeat('=', 60) . "\n";
        echo "Total bugs: {$report->totalBugs}  |  Critical: {$report->criticalBugs}\n\n";

        if ($report->totalBugs === 0) {
            echo "No bugs detected.\n";
            return;
        }

        $bySeverity = ['CRITICAL' => [], 'HIGH' => [], 'MEDIUM' => [], 'LOW' => []];
        foreach ($report->bugs as $bug) {
            $bySeverity[$bug['severity']][] = $bug;
        }

        foreach ($bySeverity as $severity => $bugs) {
            if (empty($bugs)) {
                continue;
            }

            echo "\n[{$severity}]\n";
            echo str_repeat('-', 60) . "\n";

            foreach ($bugs as $bug) {
                $location = $bug['file'];
                if ($bug['line'] !== null) {
                    $location .= ':' . $bug['line'];
                }
                $score = str_pad((string) $bug['bugScore'], 3, ' ', STR_PAD_LEFT);
                echo "  Score {$score} | {$bug['nodeId']}\n";
                echo "         Type: {$bug['type']}\n";
                echo "         {$location}\n";
            }
        }

        echo "\n";
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
