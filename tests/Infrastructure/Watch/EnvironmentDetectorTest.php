<?php

namespace Tests\Infrastructure\Watch;

use FlowEngine\Infrastructure\Watch\EnvironmentDetector;
use PHPUnit\Framework\TestCase;

final class EnvironmentDetectorTest extends TestCase
{
    public function test_detects_docker_by_dockerenv(): void
    {
        $detector = new EnvironmentDetector(
            fn(string $path) => $path === '/.dockerenv',
            fn(string $path) => ''
        );

        $this->assertTrue($detector->isDocker());
    }

    public function test_detects_docker_by_cgroup(): void
    {
        $detector = new EnvironmentDetector(
            fn(string $path) => $path === '/proc/1/cgroup',
            fn(string $path) => '12:devices:/docker/abc'
        );

        $this->assertTrue($detector->isDocker());
    }

    public function test_detects_non_docker(): void
    {
        $detector = new EnvironmentDetector(
            fn(string $path) => false,
            fn(string $path) => ''
        );

        $this->assertFalse($detector->isDocker());
    }
}
