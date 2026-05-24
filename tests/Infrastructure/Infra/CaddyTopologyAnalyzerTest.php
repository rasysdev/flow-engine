<?php

namespace Tests\Infrastructure\Infra;

use FlowEngine\Infrastructure\Infra\CaddyTopologyAnalyzer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CaddyTopologyAnalyzerTest extends TestCase
{
    private ?string $tmpDir = null;

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->tmpDir !== null && is_dir($this->tmpDir)) {
            $this->deleteDirectory($this->tmpDir);
        }
    }

    public function test_detects_multiple_generic_proxy_matches_and_ignores_symlinks(): void
    {
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'flow-engine-caddy-topology-' . uniqid('', true);
        $this->tmpDir = $base;
        mkdir($base . DIRECTORY_SEPARATOR . 'conf', 0777, true);
        if (function_exists('symlink')) {
            @symlink($base, $base . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'loop');
        }

        file_put_contents($base . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'nginx.conf', <<<NGINX
server {
  server_name one.test two.test;
  proxy_pass http://app:80;
}
server {
  server_name three.test;
  proxy_pass http://worker:8080;
}
NGINX);

        $result = (new CaddyTopologyAnalyzer())->analyze($base, 'full');

        $this->assertCount(1, $result['generic']);
        $this->assertSame(['one.test', 'two.test', 'three.test'], $result['generic'][0]['hosts']);
        $this->assertSame(['http://app:80', 'http://worker:8080'], $result['generic'][0]['upstreams']);
        $this->assertStringNotContainsString('loop', implode("\n", $result['files']));
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
