<?php

namespace FlowEngine\Infrastructure\Watch;

final class PollingWatcher implements Watcher
{
    private \Closure $hasChanged;

    public function __construct(
        callable $hasChanged
    ) {
        $this->hasChanged = \Closure::fromCallable($hasChanged);
    }

    public function waitForChange(int $intervalSeconds): bool
    {
        sleep($intervalSeconds);
        return (bool) ($this->hasChanged)();
    }

    public function type(): string
    {
        return 'polling';
    }
}
