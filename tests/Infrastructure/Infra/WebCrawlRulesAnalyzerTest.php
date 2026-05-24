<?php

namespace Tests\Infrastructure\Infra;

use FlowEngine\Infrastructure\Infra\WebCrawlRulesAnalyzer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class WebCrawlRulesAnalyzerTest extends TestCase
{
    private ?string $tmpDir = null;

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->tmpDir !== null && is_dir($this->tmpDir)) {
            $this->deleteDirectory($this->tmpDir);
        }
    }

    public function test_detects_multiple_meta_and_canonical_tags_and_ignores_symlinks(): void
    {
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'flow-engine-web-topology-' . uniqid('', true);
        $this->tmpDir = $base;
        mkdir($base . DIRECTORY_SEPARATOR . 'public', 0777, true);
        if (function_exists('symlink')) {
            @symlink($base, $base . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'loop');
        }

        file_put_contents($base . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'robots.txt', <<<ROBOTS
User-agent: *
Allow: /
Sitemap: https://example.test/sitemap.xml
ROBOTS);
        file_put_contents($base . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.html', <<<HTML
<!doctype html>
<meta name="robots" content="index,follow">
<meta name="robots" content="noarchive">
<link rel="canonical" href="https://example.test/">
<link rel="canonical" href="https://example.test/home">
HTML);

        $result = (new WebCrawlRulesAnalyzer())->analyze($base, 'full');

        $this->assertCount(1, $result['robots']);
        $this->assertSame(['index,follow', 'noarchive'], array_column($result['metaRobots'], 'content'));
        $this->assertSame(['https://example.test/', 'https://example.test/home'], array_column($result['canonicals'], 'href'));
        $this->assertStringNotContainsString('loop', implode("\n", array_column($result['metaRobots'], 'relativePath')));
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
