<?php

namespace Tests\Infrastructure\Infra;

use FlowEngine\Infrastructure\Infra\FileInventoryAnalyzer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FileInventoryAnalyzerTest extends TestCase
{
    private ?string $tmpDir = null;

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->tmpDir !== null && is_dir($this->tmpDir)) {
            $this->deleteDirectory($this->tmpDir);
        }
    }

    public function test_inventory_detects_markers_and_ignores_dependency_dirs_and_symlinks(): void
    {
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'flow-engine-file-inventory-' . uniqid('', true);
        $this->tmpDir = $base;
        mkdir($base . DIRECTORY_SEPARATOR . 'app', 0777, true);
        mkdir($base . DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR . 'pkg', 0777, true);
        mkdir($base . DIRECTORY_SEPARATOR . '.worktrees' . DIRECTORY_SEPARATOR . 'feature', 0777, true);
        if (function_exists('symlink')) {
            @symlink($base, $base . DIRECTORY_SEPARATOR . 'loop');
        }

        file_put_contents($base . DIRECTORY_SEPARATOR . 'compose.yaml', "services: {}\n");
        file_put_contents($base . DIRECTORY_SEPARATOR . 'Caddyfile', "example.test { reverse_proxy app:80 }\n");
        file_put_contents($base . DIRECTORY_SEPARATOR . 'package.json', "{}\n");
        file_put_contents($base . DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR . 'pkg' . DIRECTORY_SEPARATOR . 'Dockerfile', "FROM alpine\n");
        file_put_contents($base . DIRECTORY_SEPARATOR . '.worktrees' . DIRECTORY_SEPARATOR . 'feature' . DIRECTORY_SEPARATOR . 'Caddyfile', "ignored\n");

        $result = (new FileInventoryAnalyzer())->analyze($base, 'full');
        $relevantPaths = implode("\n", array_column($result['relevantFiles'], 'relativePath'));

        $this->assertContains('docker', $result['markers']);
        $this->assertContains('proxy', $result['markers']);
        $this->assertContains('runtime-manifest', $result['markers']);
        $this->assertNotContains('loop', $result['topLevelDirectories']);
        $this->assertStringNotContainsString('node_modules', $relevantPaths);
        $this->assertStringNotContainsString('.worktrees', $relevantPaths);
    }

    private function deleteDirectory(string $path): void
    {
        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $current = $path . DIRECTORY_SEPARATOR . $item;
            if (is_link($current) || is_file($current)) {
                if (!unlink($current)) {
                    throw new RuntimeException("Cannot delete file: {$current}");
                }
                continue;
            }

            if (is_dir($current)) {
                $this->deleteDirectory($current);
            }
        }

        @rmdir($path);
    }
}
