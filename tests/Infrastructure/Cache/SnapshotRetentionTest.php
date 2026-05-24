<?php

namespace Tests\Infrastructure\Cache;

use FlowEngine\Infrastructure\Cache\SnapshotStore;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestProjectContext;

final class SnapshotRetentionTest extends TestCase
{
    private string $tempDir;
    private string $stateBase;
    private string $snapshotDir;
    private string $originalStateDirEnv;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'snapshot-retention-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);

        $this->stateBase = $this->tempDir . DIRECTORY_SEPARATOR . 'state-base';
        mkdir($this->stateBase, 0777, true);

        $this->originalStateDirEnv = getenv('FLOW_ENGINE_STATE_DIR') ?: '';
        putenv('FLOW_ENGINE_STATE_DIR=' . $this->stateBase);

        $canonical = realpath($this->tempDir) ?: $this->tempDir;
        $projectId = sha1($canonical);
        $this->snapshotDir = $this->stateBase . DIRECTORY_SEPARATOR . $projectId
            . DIRECTORY_SEPARATOR . '.flow-engine' . DIRECTORY_SEPARATOR . 'snapshots';
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);

        if ($this->originalStateDirEnv === '') {
            putenv('FLOW_ENGINE_STATE_DIR');
        } else {
            putenv('FLOW_ENGINE_STATE_DIR=' . $this->originalStateDirEnv);
        }
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

    private function makeStore(?int $keepMax): SnapshotStore
    {
        return new SnapshotStore(
            new TestProjectContext($this->tempDir),
            $keepMax
        );
    }

    /**
     * Save snapshots and assign explicit, deterministic mtimes so that list()
     * sorting (newest-first by filemtime) is reliable regardless of wall-clock speed.
     *
     * Base time is in the past so real "just-created" times are always newer.
     * Each file gets base + (index * 60) seconds — monotonically increasing.
     */
    private function saveWithFixedMtimes(SnapshotStore $store, array $labels): void
    {
        // A fixed point in the past so every touch() value is well below the
        // real current time that the file has right after file_put_contents().
        $baseTime = mktime(0, 0, 0, 1, 1, 2025);

        foreach ($labels as $i => $label) {
            $store->save($label, ['label' => $label]);
            // Touch AFTER save() so the file exists; the next save()'s pruning
            // will see this mtime when it calls list().
            $path = $this->snapshotDir . DIRECTORY_SEPARATOR . $label . '.json.gz';
            touch($path, $baseTime + ($i * 60));
            clearstatcache(true, $path);
        }
    }

    // -------------------------------------------------------------------------

    public function test_no_pruning_when_keepMax_null(): void
    {
        $store = $this->makeStore(null);
        $this->saveWithFixedMtimes($store, ['a', 'b', 'c', 'd', 'e', 'f']);

        $this->assertCount(6, $store->list());
    }

    public function test_auto_prunes_to_keepMax(): void
    {
        $store = $this->makeStore(3);
        $this->saveWithFixedMtimes($store, ['a', 'b', 'c', 'd', 'e']);

        $this->assertCount(3, $store->list(), 'Only 3 snapshots should remain after pruning');
    }

    public function test_keeps_newest_deletes_oldest(): void
    {
        $store = $this->makeStore(3);
        $this->saveWithFixedMtimes($store, ['first', 'second', 'third', 'fourth', 'fifth']);

        $labels = array_column($store->list(), 'label');

        // 'fifth', 'fourth', 'third' are the 3 newest (highest mtimes).
        // 'first' and 'second' should have been pruned.
        $this->assertContains('fifth', $labels, 'newest should survive');
        $this->assertContains('fourth', $labels, 'second-newest should survive');
        $this->assertContains('third', $labels, 'third-newest should survive');
        $this->assertNotContains('first', $labels, 'oldest should be pruned');
        $this->assertNotContains('second', $labels, 'second-oldest should be pruned');
    }

    public function test_pruning_only_fires_after_save(): void
    {
        $store = $this->makeStore(2);
        $this->saveWithFixedMtimes($store, ['x', 'y']);

        $this->assertCount(2, $store->list());

        $store->save('z', ['data' => 'z']);

        $this->assertCount(2, $store->list());
    }

    public function test_keepmax_of_one_keeps_only_latest(): void
    {
        $store = $this->makeStore(1);
        $this->saveWithFixedMtimes($store, ['old1', 'old2', 'newest']);

        $labels = array_column($store->list(), 'label');
        $this->assertSame(['newest'], $labels);
    }
}
