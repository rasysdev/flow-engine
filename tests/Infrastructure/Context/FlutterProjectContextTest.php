<?php

namespace Tests\Infrastructure\Context;

use FlowEngine\Infrastructure\Context\FlutterProjectContext;
use PHPUnit\Framework\TestCase;

final class FlutterProjectContextTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/flutter-context-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
        mkdir($this->tempDir . '/backend/app', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    public function test_uses_flutter_defaults_when_built_from_root_path(): void
    {
        $context = new FlutterProjectContext($this->tempDir);

        self::assertSame(['lib', 'test', 'integration_test', 'backend/app'], $context->includePaths());
        self::assertSame(['dart', 'py'], $context->extensions());
        self::assertContains('.dart_tool', $context->ignoredPaths());
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }

        rmdir($dir);
    }
}
