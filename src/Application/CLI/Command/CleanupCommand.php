<?php

namespace FlowEngine\Application\CLI\Command;

use FlowEngine\Bootstrap\Container;
use FlowEngine\Console\ConsoleIO;
use Throwable;

final class CleanupCommand implements Command
{
    public function __construct(
        private ConsoleIO $io
    ) {
    }

    public function supports(string $command): bool
    {
        return $command === 'cleanup';
    }

    public function handle(array $argv): void
    {
        $projectPath = $argv[2] ?? null;

        if (!$projectPath) {
            $this->io->error('Usage: flow cleanup <project_path> [--older-than=<days>] [--keep-last=<count>] [--stats]');
            return;
        }

        $olderThan = $this->extractOption($argv, '--older-than');
        $keepLast = $this->extractOption($argv, '--keep-last');
        $showStats = in_array('--stats', $argv, true);

        if (!$olderThan && !$keepLast && !$showStats) {
            $this->io->error('Specify at least one option: --older-than, --keep-last, or --stats');
            $this->io->info('Examples:');
            $this->io->info('  flow cleanup . --older-than=30');
            $this->io->info('  flow cleanup . --keep-last=10');
            $this->io->info('  flow cleanup . --stats');
            return;
        }

        try {
            $store = (new Container($projectPath))->snapshotStore();

            if ($showStats) {
                $this->showStats($store);
                return;
            }

            $deleted = 0;

            if ($olderThan !== null) {
                $days = (int) $olderThan;
                $deleted = method_exists($store, 'deleteOlderThan') ? $store->deleteOlderThan($days) : 0;
                $this->io->json([
                    'status' => 'ok',
                    'action' => 'delete_older_than',
                    'days' => $days,
                    'deleted' => $deleted,
                ]);
                return;
            }

            if ($keepLast !== null) {
                $count = (int) $keepLast;
                $deleted = method_exists($store, 'deleteKeepLast') ? $store->deleteKeepLast($count) : 0;
                $this->io->json([
                    'status' => 'ok',
                    'action' => 'keep_last',
                    'keep_count' => $count,
                    'deleted' => $deleted,
                ]);
                return;
            }
        } catch (Throwable $e) {
            $this->io->error('Cleanup failed: ' . $e->getMessage());
        }
    }

    private function showStats(object $store): void
    {
        if (!method_exists($store, 'stats')) {
            $this->io->error('Snapshot store does not support stats.');
            return;
        }

        $stats = $store->stats();

        $this->io->json([
            'status' => 'ok',
            'stats' => $stats,
        ]);
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
}
