<?php

namespace Tests\Infrastructure\Cache;

use FlowEngine\Infrastructure\Cache\PerFileCache;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestProjectContext;

final class PerFileCacheTest extends TestCase
{
    private string $tempDir;
    private PerFileCache $cache;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/per-file-cache-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);

        $this->cache = new PerFileCache(new TestProjectContext($this->tempDir));
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    // -------------------------------------------------------------------------
    // load() when no cache file exists
    // -------------------------------------------------------------------------

    public function test_returns_empty_when_no_cache_file(): void
    {
        $result = $this->cache->load();
        $this->assertSame([], $result);
    }

    // -------------------------------------------------------------------------
    // save() + load() round-trip
    // -------------------------------------------------------------------------

    public function test_save_and_load_roundtrip(): void
    {
        $nodeArr = [
            'class'  => 'MyClass',
            'method' => 'myMethod',
            'file'   => '/some/file.php',
            'line'   => 42,
            'lang'   => 'php',
            'meta'   => null,
        ];
        $edgeArr = [
            'from'   => 'MyClass::myMethod',
            'to'     => 'OtherClass::otherMethod',
            'method' => 'otherMethod',
            'type'   => 'method_call',
        ];

        $fp  = '/some/file.php|1708300000|4096';
        $map = [
            '/some/file.php' => [
                'fp'    => $fp,
                'nodes' => [$nodeArr],
                'edges' => [$edgeArr],
            ],
        ];

        $this->cache->save($map);
        $loaded = $this->cache->load();

        $this->assertArrayHasKey('/some/file.php', $loaded);
        $this->assertSame($fp, $loaded['/some/file.php']['fp']);
        $this->assertSame([$nodeArr], $loaded['/some/file.php']['nodes']);
        $this->assertSame([$edgeArr], $loaded['/some/file.php']['edges']);
    }

    // -------------------------------------------------------------------------
    // fingerprint()
    // -------------------------------------------------------------------------

    public function test_missing_file_fingerprint(): void
    {
        $path = $this->tempDir . '/nonexistent.php';
        $fp   = PerFileCache::fingerprint($path);

        $this->assertSame($path . '|missing', $fp);
    }

    public function test_fingerprint_stable_for_unchanged_file(): void
    {
        $path = $this->tempDir . '/stable.php';
        file_put_contents($path, '<?php echo 1;');

        $fp1 = PerFileCache::fingerprint($path);
        $fp2 = PerFileCache::fingerprint($path);

        $this->assertSame($fp1, $fp2);
    }

    public function test_fingerprint_changes_on_mtime(): void
    {
        $path = $this->tempDir . '/mtime.php';
        file_put_contents($path, '<?php echo 1;');

        $fp1 = PerFileCache::fingerprint($path);

        // Force a different mtime by touching the file one second in the future
        touch($path, time() + 1);
        clearstatcache(true, $path);

        $fp2 = PerFileCache::fingerprint($path);

        $this->assertNotSame($fp1, $fp2);
    }

    public function test_fingerprint_format_contains_path_mtime_size(): void
    {
        $path = $this->tempDir . '/info.php';
        file_put_contents($path, '<?php');

        $fp   = PerFileCache::fingerprint($path);
        $parts = explode('|', $fp);

        $this->assertCount(3, $parts);
        $this->assertSame($path, $parts[0]);
        $this->assertIsNumeric($parts[1]);  // mtime
        $this->assertIsNumeric($parts[2]);  // size
    }
}
