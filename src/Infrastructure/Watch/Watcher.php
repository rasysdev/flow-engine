<?php

namespace FlowEngine\Infrastructure\Watch;

interface Watcher
{
    /**
     * Blocks until a change event occurs, then returns true.
     */
    public function waitForChange(int $intervalSeconds): bool;

    /**
     * Returns a string describing the watcher implementation.
     */
    public function type(): string;
}
