<?php

namespace Tests\Infrastructure\Diff;

use FlowEngine\Domain\Flow\Flow;
use FlowEngine\Infrastructure\Analyzer\FilesystemProjectScanner;
use FlowEngine\Infrastructure\Cache\FlowCache;
use FlowEngine\Infrastructure\Context\InferredReadOnlyProjectContext;
use FlowEngine\Infrastructure\Diff\StalenessChecker;
use PHPUnit\Framework\TestCase;

final class StalenessCheckerTest extends TestCase
{
    private string $tempDir;
    private string $oldStateDir = '';

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/flow-engine-staleness-' . uniqid('', true);
        mkdir($this->tempDir, 0777, true);
        $this->oldStateDir = getenv('FLOW_ENGINE_STATE_DIR') ?: '';
        putenv('FLOW_ENGINE_STATE_DIR=' . $this->tempDir . '/state');
    }

    protected function tearDown(): void
    {
        putenv($this->oldStateDir === '' ? 'FLOW_ENGINE_STATE_DIR' : 'FLOW_ENGINE_STATE_DIR=' . $this->oldStateDir);
        $this->removeDir($this->tempDir);
    }

    public function test_ignores_cached_files_that_are_now_outside_current_scan_scope(): void
    {
        $project = $this->tempDir . '/project';
        $srcFile = $project . '/src/Keep.php';
        $worktreeFile = $project . '/.worktrees/feature/src/Old.php';
        $this->writeFile($srcFile, "<?php\n");
        $this->writeFile($worktreeFile, "<?php\n");

        $oldContext = new InferredReadOnlyProjectContext(
            rootPath: $project,
            includePaths: ['.'],
            ignoredPaths: ['.git'],
            extensions: ['php'],
        );
        $cache = new FlowCache($oldContext);
        $cache->saveFlow(new Flow([], []), [$srcFile, $worktreeFile], $project . '/flow-engine.json');

        $currentContext = new InferredReadOnlyProjectContext(
            rootPath: $project,
            includePaths: ['src'],
            ignoredPaths: ['.git', '.worktrees'],
            extensions: ['php'],
        );

        $report = (new StalenessChecker($cache, new FilesystemProjectScanner(), $currentContext))->execute();

        self::assertFalse($report->stale);
        self::assertSame([], $report->deletedFiles);
    }

    private function writeFile(string $path, string $content): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($path, $content);
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
