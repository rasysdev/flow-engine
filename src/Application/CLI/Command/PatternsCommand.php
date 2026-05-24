<?php

namespace FlowEngine\Application\CLI\Command;

use FlowEngine\Application\DTO\PatternReportDTO;
use FlowEngine\Bootstrap\Container;
use FlowEngine\Console\ConsoleIO;

/**
 * CLI command: flow patterns <project_path> [--format=text|json] [--pattern=repository|factory|strategy|observer|command|adapter]
 *
 * Detecta padrões de projeto (Design Patterns) no grafo do projeto.
 *
 * Exemplos:
 *   flow patterns .
 *   flow patterns . --format=json
 *   flow patterns . --pattern=repository
 */
final class PatternsCommand implements Command
{
    private const VALID_PATTERNS = [
        'repository', 'factory', 'strategy', 'observer', 'command', 'adapter', 'all',
    ];

    public function __construct(
        private readonly ConsoleIO $io
    ) {
    }

    public function supports(string $command): bool
    {
        return $command === 'patterns';
    }

    public function handle(array $argv): void
    {
        $projectPath = $argv[2] ?? null;

        if (!$projectPath) {
            $this->io->error(
                'Usage: flow patterns <project_path> [--format=text|json] [--pattern=repository|factory|strategy|observer|command|adapter|all]'
            );
            return;
        }

        $format  = $this->extractOption($argv, '--format') ?? 'text';
        $pattern = strtolower($this->extractOption($argv, '--pattern') ?? 'all');

        if (!in_array($pattern, self::VALID_PATTERNS, true)) {
            $this->io->error('Invalid --pattern. Use: ' . implode(', ', self::VALID_PATTERNS));
            return;
        }

        $container = new Container($projectPath);
        $container->analyzeProject()->execute();

        $report = $container->detectPatterns()->execute();

        if ($format === 'json') {
            $data = $pattern === 'all'
                ? $report->toArray()
                : ['totalDetected' => count($report->patterns[$pattern] ?? []), $pattern => $report->patterns[$pattern] ?? []];

            echo json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            return;
        }

        $this->printText($report, $pattern);
    }

    private function printText(PatternReportDTO $report, string $filter): void
    {
        echo "\nDesign Pattern Detection Report\n";
        echo str_repeat('=', 60) . "\n";
        echo "Total patterns detected: {$report->totalDetected}\n\n";

        if ($report->totalDetected === 0) {
            echo "No design patterns detected.\n\n";
            return;
        }

        echo "Summary:\n";
        foreach ($report->summary as $name => $count) {
            if ($filter !== 'all' && $name !== $filter) {
                continue;
            }
            $indicator = $count > 0 ? '✓' : '·';
            echo "  {$indicator} " . ucfirst($name) . ": {$count}\n";
        }
        echo "\n";

        $labels = [
            'repository' => 'Repository Pattern',
            'factory'    => 'Factory Pattern',
            'strategy'   => 'Strategy Pattern',
            'observer'   => 'Observer Pattern',
            'command'    => 'Command Pattern',
            'adapter'    => 'Adapter Pattern',
        ];

        foreach ($report->patterns as $name => $occurrences) {
            if ($filter !== 'all' && $name !== $filter) {
                continue;
            }

            if ($occurrences === []) {
                continue;
            }

            $label = $labels[$name] ?? ucfirst($name);
            echo "[{$label}] — " . count($occurrences) . " occurrence(s)\n";
            echo str_repeat('-', 60) . "\n";

            foreach ($occurrences as $item) {
                $class      = $item['class'] ?? $item['interface'] ?? '?';
                $confidence = $item['confidence'] ?? 'MEDIUM';
                $note       = $item['note'] ?? '';

                echo "  [{$confidence}] {$class}\n";

                if (!empty($item['methods'])) {
                    echo '         Methods: ' . implode(', ', $item['methods']) . "\n";
                }
                if (!empty($item['factoryMethods'])) {
                    echo '         Factory methods: ' . implode(', ', $item['factoryMethods']) . "\n";
                }
                if (!empty($item['implementations'])) {
                    echo '         Implementations (' . count($item['implementations']) . '): '
                        . implode(', ', array_map(fn($i) => $this->shortName($i), $item['implementations'])) . "\n";
                }
                if (!empty($item['eventMethods'])) {
                    echo '         Events: ' . implode(', ', $item['eventMethods']) . "\n";
                }
                if (!empty($item['executor'])) {
                    echo "         Executor: {$item['executor']}()\n";
                }
                if ($note !== '') {
                    echo "         Note: {$note}\n";
                }
                echo "\n";
            }
        }
    }

    private function shortName(string $fqn): string
    {
        $parts = explode('\\', $fqn);
        return end($parts);
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
