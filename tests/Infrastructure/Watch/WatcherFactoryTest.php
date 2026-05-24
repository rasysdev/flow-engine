<?php

namespace Tests\Infrastructure\Watch;

use FlowEngine\Infrastructure\Watch\EnvironmentDetector;
use FlowEngine\Infrastructure\Watch\PollingWatcher;
use FlowEngine\Infrastructure\Watch\WatcherFactory;
use PHPUnit\Framework\TestCase;

final class WatcherFactoryTest extends TestCase
{
    public function test_auto_uses_polling_in_docker(): void
    {
        $environment = new EnvironmentDetector(
            fn(string $path) => $path === '/.dockerenv',
            fn(string $path) => ''
        );

        $factory = new WatcherFactory($environment, false);
        $watcher = $factory->create('auto', fn() => false, ['.']);

        $this->assertInstanceOf(PollingWatcher::class, $watcher);
    }

    public function test_native_falls_back_to_polling_when_unavailable(): void
    {
        $environment = new EnvironmentDetector(
            fn(string $path) => false,
            fn(string $path) => ''
        );

        $factory = new WatcherFactory($environment, false);
        $watcher = $factory->create('native', fn() => false, ['.']);

        $this->assertInstanceOf(PollingWatcher::class, $watcher);
    }

    public function test_polling_mode_always_returns_polling(): void
    {
        $environment = new EnvironmentDetector(
            fn(string $path) => false,
            fn(string $path) => ''
        );

        $factory = new WatcherFactory($environment, false);
        $watcher = $factory->create('polling', fn() => false, ['.']);

        $this->assertInstanceOf(PollingWatcher::class, $watcher);
    }
}
