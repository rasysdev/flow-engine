<?php

namespace Tests\Infrastructure\Infra;

use FlowEngine\Infrastructure\Infra\ScriptTopologyAnalyzer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ScriptTopologyAnalyzerTest extends TestCase
{
    private ?string $tmpDir = null;

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->tmpDir !== null && is_dir($this->tmpDir)) {
            $this->deleteDirectory($this->tmpDir);
        }
    }

    public function test_docker_compose_file_references_keep_directory_prefix(): void
    {
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'flow-engine-script-topology-' . uniqid('', true);
        $this->tmpDir = $base;
        mkdir($base . DIRECTORY_SEPARATOR . 'deploy', 0777, true);
        if (function_exists('symlink')) {
            @symlink($base, $base . DIRECTORY_SEPARATOR . 'deploy' . DIRECTORY_SEPARATOR . 'loop');
        }

        file_put_contents($base . DIRECTORY_SEPARATOR . 'deploy' . DIRECTORY_SEPARATOR . 'docker-compose.yml', <<<YAML
services:
  app:
    image: nginx:1.27
YAML);
        file_put_contents($base . DIRECTORY_SEPARATOR . 'deploy' . DIRECTORY_SEPARATOR . 'compose.override.yml', <<<YAML
services:
  worker:
    image: alpine:3.20
YAML);
        file_put_contents($base . DIRECTORY_SEPARATOR . 'deploy.sh', <<<SH
#!/bin/bash
set -e
docker compose -f deploy/docker-compose.yml -f deploy/compose.override.yml up -d
systemctl restart app.service
systemctl reload worker.service
SH);

        $result = (new ScriptTopologyAnalyzer())->analyze($base, 'full');

        $this->assertCount(1, $result['scripts']);
        $this->assertSame(['deploy/docker-compose.yml', 'deploy/compose.override.yml'], $result['scripts'][0]['composeFiles']);
        $this->assertSame(['app.service', 'worker.service'], $result['scripts'][0]['systemctlTargets']);
        $this->assertContains('compose:deploy/docker-compose.yml', array_column($result['edges'], 'to'));
        $this->assertContains('compose:deploy/compose.override.yml', array_column($result['edges'], 'to'));
        $this->assertNotContains('loop/deploy.sh', array_column($result['scripts'], 'relativePath'));
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
                continue;
            }

            if (file_exists($current) && !unlink($current)) {
                throw new RuntimeException("Cannot delete file: {$current}");
            }
        }

        @rmdir($path);
    }
}
