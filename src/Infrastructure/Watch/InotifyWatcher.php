<?php

namespace FlowEngine\Infrastructure\Watch;

final class InotifyWatcher implements Watcher
{
    /** @var resource */
    private $inotify;

    /** @var array<int, string> */
    private array $watches = [];

    /**
     * @param string[] $paths
     */
    public function __construct(array $paths)
    {
        if (!function_exists('inotify_init')) {
            throw new \RuntimeException('inotify extension not available');
        }

        $this->inotify = inotify_init();
        stream_set_blocking($this->inotify, true);

        foreach ($paths as $path) {
            if (is_dir($path)) {
                $this->watches[] = inotify_add_watch(
                    $this->inotify,
                    $path,
                    IN_CREATE | IN_MODIFY | IN_DELETE | IN_MOVED_FROM | IN_MOVED_TO
                );
            }
        }
    }

    public function waitForChange(int $intervalSeconds): bool
    {
        if (!empty($intervalSeconds)) {
            stream_set_timeout($this->inotify, $intervalSeconds);
        }

        $events = inotify_read($this->inotify);
        return !empty($events);
    }

    public function type(): string
    {
        return 'native';
    }
}
