<?php

namespace Tests\Application\Service;

use FlowEngine\Application\Service\OrphanAuditService;
use PHPUnit\Framework\TestCase;

final class OrphanAuditServiceTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/orphan-audit-' . uniqid('', true);
        mkdir($this->root, 0777, true);
        mkdir($this->root . '/src', 0777, true);
        mkdir($this->root . '/tests', 0777, true);
        mkdir($this->root . '/docs', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    public function test_classifies_as_false_positive_when_source_reference_exists(): void
    {
        file_put_contents($this->root . '/src/Caller.php', "<?php\nPromptFactory::build();\n");

        $audit = (new OrphanAuditService($this->root, []))->audit([
            [
                'nodeId' => 'App\\PromptFactory::build',
                'safeToRemove' => true,
                'confidence' => 1.0,
            ],
        ]);

        $this->assertSame('falso_positivo', $audit['orphans'][0]['classification']);
        $this->assertGreaterThan(0, $audit['orphans'][0]['evidence']['internalRefs']);
    }

    public function test_classifies_as_abandonado_when_safe_and_no_evidence(): void
    {
        $audit = (new OrphanAuditService($this->root, []))->audit([
            [
                'nodeId' => 'App\\Legacy\\OldService::run',
                'safeToRemove' => true,
                'confidence' => 1.0,
            ],
        ]);

        $this->assertSame('abandonado', $audit['orphans'][0]['classification']);
    }

    public function test_classifies_as_investigar_when_only_test_reference_exists(): void
    {
        file_put_contents($this->root . '/tests/OldServiceTest.php', "<?php\nOldService::run();\n");

        $audit = (new OrphanAuditService($this->root, []))->audit([
            [
                'nodeId' => 'App\\Legacy\\OldService::run',
                'safeToRemove' => true,
                'confidence' => 1.0,
            ],
        ]);

        $this->assertSame('investigar', $audit['orphans'][0]['classification']);
        $this->assertGreaterThan(0, $audit['orphans'][0]['evidence']['testRefs']);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }
}
