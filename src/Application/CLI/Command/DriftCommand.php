<?php

namespace FlowEngine\Application\CLI\Command;

use FlowEngine\Application\UseCase\DetectDrift;
use FlowEngine\Application\UseCase\EvaluateDriftPolicy;
use FlowEngine\Bootstrap\Container;
use FlowEngine\Console\ConsoleIO;
use Throwable;

final class DriftCommand implements Command
{
    public function __construct(
        private ConsoleIO $io
    ) {
    }

    public function supports(string $command): bool
    {
        return $command === 'drift';
    }

    public function handle(array $argv): void
    {
        $projectPath = $argv[2] ?? null;

        if (!$projectPath) {
            $this->io->error('Usage: flow drift <project_path> --baseline=<label>');
            return;
        }

        $baselineLabel = $this->extractOption($argv, '--baseline');
        $policyPath = $this->extractOption($argv, '--policy');

        if (!$baselineLabel) {
            $this->io->error('Missing required option: --baseline=<label>');
            $this->io->info('Example: flow drift . --baseline=pre-refactor');
            $this->io->info('With policy: flow drift . --baseline=pre-refactor --policy=drift-policy.json');
            return;
        }

        try {
            $container = new Container($projectPath);

            // Analyze current state
            $container->analyzeProject()->execute();

            // Get current metrics/violations for comparison
            $currentSnapshot = $this->buildCurrentSnapshot($container);

            // Detect drift
            $detector = new DetectDrift($container->snapshotStore());
            $result = $detector->execute($baselineLabel, $currentSnapshot);

            if ($result['status'] === 'error') {
                $this->io->error($result['message'] ?? 'Unknown error');
                return;
            }

            $output = [
                'status' => 'ok',
                'drift' => $result['drift'],
                'baseline' => $result['baseline'],
                'current' => $result['current'],
            ];

            // Evaluate policy if provided
            if ($policyPath !== null) {
                $policy = $this->loadPolicy($policyPath);
                if ($policy !== null) {
                    $evaluator = new EvaluateDriftPolicy();
                    $evaluation = $evaluator->execute($result['drift'], $policy);
                    $output['policy'] = $evaluation;
                }
            }

            $this->io->json($output);
        } catch (Throwable $e) {
            $this->io->error('Failed to detect drift: ' . $e->getMessage());
        }
    }

    private function buildCurrentSnapshot(Container $container): array
    {
        $snapshot = [];

        // Get metrics
        try {
            $metrics = $container->analyzeMetrics()->execute()->toArray();
            $snapshot['metrics'] = $metrics;
        } catch (Throwable) {
            $snapshot['metrics'] = [];
        }

        // Get violations
        try {
            $arch = $container->analyzeArchitecture()->execute()->toArray();
            $snapshot['architectureViolations'] = $arch['violations'] ?? [];
        } catch (Throwable) {
            $snapshot['architectureViolations'] = [];
        }

        // Get cycles
        try {
            $cycles = $container->analyzeCycles()->execute()->toArray();
            $snapshot['cycles'] = $cycles['cycles'] ?? [];
        } catch (Throwable) {
            $snapshot['cycles'] = [];
        }

        // Get orphans
        try {
            $orphans = $container->analyzeOrphans()->execute()->toArray();
            $snapshot['orphans'] = $orphans['orphans'] ?? [];
        } catch (Throwable) {
            $snapshot['orphans'] = [];
        }

        return $snapshot;
    }

    private function extractOption(array $argv, string $key): ?string
    {
        foreach ($argv as $arg) {
            if (str_starts_with($arg, $key . '=')) {
                return substr($arg, strlen($key) + 1);
            }
        }
        return null;
    }

    private function loadPolicy(string $path): ?array
    {
        $resolved = realpath($path);
        if ($resolved === false || !file_exists($resolved)) {
            return null;
        }

        $content = file_get_contents($resolved);
        if ($content === false) {
            return null;
        }

        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : null;
    }
}
