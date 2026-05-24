<?php

namespace FlowEngine\Infrastructure\Watch;

final class WatcherFactory
{
    public function __construct(
        private EnvironmentDetector $environment,
        private bool $inotifyAvailable
    ) {
    }

    public static function createDefault(): self
    {
        return new self(
            EnvironmentDetector::createDefault(),
            function_exists('inotify_init')
        );
    }

    /**
     * @param callable():bool $hasChanged
     * @param string[] $paths
     */
    public function create(string $mode, callable $hasChanged, array $paths): Watcher
    {
        return match ($mode) {
            'polling' => new PollingWatcher($hasChanged),
            'native' => $this->createNative($paths, $hasChanged),
            default => $this->createAuto($paths, $hasChanged),
        };
    }

    /**
     * @param string[] $paths
     */
    private function createAuto(array $paths, callable $hasChanged): Watcher
    {
        if ($this->environment->isDocker()) {
            return new PollingWatcher($hasChanged);
        }

        if ($this->inotifyAvailable) {
            try {
                return new InotifyWatcher($paths);
            } catch (\Throwable $e) {
                return new PollingWatcher($hasChanged);
            }
        }

        return new PollingWatcher($hasChanged);
    }

    /**
     * @param string[] $paths
     */
    private function createNative(array $paths, callable $hasChanged): Watcher
    {
        if ($this->inotifyAvailable) {
            return new InotifyWatcher($paths);
        }

        return new PollingWatcher($hasChanged);
    }
}
