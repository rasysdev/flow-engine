<?php

namespace FlowEngine\Application\CLI\Command;

use FlowEngine\AI\Export\MarkdownFormatter;
use FlowEngine\Bootstrap\Container;
use FlowEngine\Console\ConsoleIO;

final class SimulateCommand implements Command
{
    public function __construct(
        private ConsoleIO $io
    ) {
    }

    public function supports(string $command): bool
    {
        return $command === 'simulate';
    }

    public function handle(array $argv): void
    {
        $projectPath = $argv[2] ?? null;
        $scopeArg    = $this->extractOption($argv, '--scope') ?? 'hotspots:20';
        $nodesArg    = $this->extractOption($argv, '--nodes');
        $format      = $this->extractOption($argv, '--format') ?? 'json';

        if (!$projectPath) {
            $this->io->error(
                'Usage: flow simulate <project_path> ' .
                '[--scope=hotspots:<N>|namespace:<Ns>|all] ' .
                '[--nodes=<id1,id2,...>] ' .
                '[--format=json|markdown]'
            );
            return;
        }

        if (!in_array($format, ['json', 'markdown'], true)) {
            $this->io->error('Invalid format. Use --format=json or --format=markdown');
            return;
        }

        // Resolve scope and explicit node list
        $explicitNodeIds = [];
        if ($nodesArg !== null) {
            $explicitNodeIds = array_filter(array_map('trim', explode(',', $nodesArg)));
            $scope = 'explicit';
        } else {
            $scope = $scopeArg;
        }

        try {
            $container = new Container($projectPath);
            $container->analyzeProject()->execute();

            $result = $container->simulateLargeScaleRefactor()->execute($scope, $explicitNodeIds);

            if ($format === 'markdown') {
                $formatter = new MarkdownFormatter();
                echo $formatter->formatSimulation($result);
            } else {
                $this->io->json($result->toArray());
            }
        } catch (\Throwable $e) {
            $this->io->error('Simulation failed: ' . $e->getMessage());
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
