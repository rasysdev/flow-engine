<?php

namespace FlowEngine\Application\CLI\Command;

use FlowEngine\Bootstrap\Container;

final class WatchCommand implements Command
{
    public function __construct(
        private \FlowEngine\Console\ConsoleIO $io
    ) {
    }

    public function supports(string $command): bool
    {
        return $command === 'watch';
    }

    public function handle(array $argv): void
    {
        $projectPath = $argv[2] ?? null;
        $analysis = $argv[3] ?? 'metrics';
        $interval = $this->parseInterval($argv) ?? 2;
        $watcherMode = $this->parseWatcherMode($argv) ?? 'auto';

        if (!$projectPath) {
            $this->io->error('Usage: flow watch <project_path> [analysis] [--interval=2] [--watcher=auto|polling|native]');
            return;
        }

        $container = new Container($projectPath);
        $container->analyzeProject()->execute();

        $cache = $container->flowCache();
        $configPath = rtrim($projectPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'flow-engine.json';
        $files = $this->scanFiles($container);

        $hasChanged = function () use ($cache, $files, $configPath): bool {
            return !$cache->isValid($files, $configPath);
        };

        $watcher = $container->watcherFactory()
            ->create($watcherMode, $hasChanged, $this->watchPaths($projectPath, $files));

        $this->io->json([
            'event' => 'watcher',
            'mode' => $watcherMode,
            'selected' => $watcher->type(),
        ]);

        while (true) {
            if (!$watcher->waitForChange($interval)) {
                continue;
            }

            $container = new Container($projectPath);
            $container->analyzeProject()->execute();
            $payload = $this->runAnalysis($container, $analysis);

            $this->io->json([
                'event' => 'change',
                'timestamp' => date('c'),
                'analysis' => $analysis,
                'hash' => $container->cacheHash(),
                'report' => $payload,
            ]);
        }
    }

    private function parseInterval(array $argv): ?int
    {
        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--interval=')) {
                $value = (int) substr($arg, strlen('--interval='));
                return $value > 0 ? $value : null;
            }
        }

        return null;
    }

    private function parseWatcherMode(array $argv): ?string
    {
        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--watcher=')) {
                $value = substr($arg, strlen('--watcher='));
                return $value !== '' ? $value : null;
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function scanFiles(Container $container): array
    {
        $flow = $container->getFlow();
        $files = [];

        foreach ($flow->nodes() as $node) {
            $files[] = $node->file();
        }

        return array_values(array_unique($files));
    }

    /**
     * @param string[] $files
     * @return string[]
     */
    private function watchPaths(string $projectPath, array $files): array
    {
        $paths = [$projectPath];

        foreach ($files as $file) {
            $dir = dirname($file);
            $paths[] = $dir;
        }

        return array_values(array_unique($paths));
    }

    /**
     * @return array<string, mixed>
     */
    private function runAnalysis(Container $container, string $analysis): array
    {
        return match ($analysis) {
            'complexity' => $container->analyzeComplexity()->execute()->toArray(),
            'cycles' => $container->analyzeCycles()->execute()->toArray(),
            'architecture' => $container->analyzeArchitecture()->execute()->toArray(),
            'metrics' => $container->analyzeMetrics()->execute()->toArray(),
            'orphans' => $container->analyzeOrphans()->execute()->toArray(),
            default => [
                'status' => 'error',
                'message' => "Unknown analysis: {$analysis}",
            ],
        };
    }
}
