<?php

namespace Tests\Application\UseCase;

use FlowEngine\Application\DTO\RemediationProposalDTO;
use FlowEngine\Application\DTO\RemediationProposalReportDTO;
use FlowEngine\Application\UseCase\SaveRemediationProposals;
use FlowEngine\Infrastructure\Cache\SnapshotStore;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestProjectContext;

final class SaveRemediationProposalsTest extends TestCase
{
    private string $tempDir;
    private SnapshotStore $store;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/save-remediation-test-' . uniqid('', true);
        mkdir($this->tempDir, 0777, true);
        $this->store = new SnapshotStore(new TestProjectContext($this->tempDir));
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    public function test_saves_report_with_expected_snapshot_shape(): void
    {
        $report = new RemediationProposalReportDTO(
            generatedAt: '2026-02-23T00:00:00+00:00',
            total: 1,
            byCategory: ['architecture' => 1],
            proposals: [
                new RemediationProposalDTO(
                    id: 'arch-001',
                    category: 'architecture',
                    priority: 'P1',
                    target: 'A -> B',
                    title: 'Break forbidden dependency',
                    reason: 'Violation',
                    actions: ['Introduce boundary'],
                    expectedImpact: ['reduceArchitectureViolationsBy' => 1],
                    requiresApproval: true
                ),
            ]
        );

        $useCase = new SaveRemediationProposals($this->store);
        $useCase->execute('remediation-plan', $report);

        $snapshot = $this->store->load('remediation-plan');
        $this->assertSame('remediation_proposals', $snapshot['type']);
        $this->assertSame(1, $snapshot['report']['total']);
        $this->assertSame('arch-001', $snapshot['report']['proposals'][0]['id']);
        $this->assertArrayHasKey('savedAt', $snapshot);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDir($path);
                continue;
            }

            unlink($path);
        }

        rmdir($dir);
    }
}

