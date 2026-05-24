<?php

namespace Tests\Application\UseCase;

use FlowEngine\Application\UseCase\GetRemediationProposalStatus;
use FlowEngine\Infrastructure\Cache\SnapshotStore;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestProjectContext;

final class GetRemediationProposalStatusTest extends TestCase
{
    private string $tempDir;
    private SnapshotStore $store;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/status-remediation-test-' . uniqid('', true);
        mkdir($this->tempDir, 0777, true);
        $this->store = new SnapshotStore(new TestProjectContext($this->tempDir));
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    public function test_reports_all_pending_when_no_approvals_exist(): void
    {
        $this->savePlan('remediation-plan');
        $useCase = new GetRemediationProposalStatus($this->store);

        $status = $useCase->execute('remediation-plan');

        $this->assertSame('remediation-plan', $status['planLabel']);
        $this->assertSame(2, $status['total']);
        $this->assertSame(0, $status['approvedCount']);
        $this->assertSame(2, $status['pendingCount']);
        $this->assertFalse($status['proposals'][0]['approved']);
        $this->assertFalse($status['proposals'][1]['approved']);
    }

    public function test_reports_approved_and_pending_counts(): void
    {
        $this->savePlan('remediation-plan');
        $this->store->save('remediation-plan-approvals', [
            'type' => 'remediation_approvals',
            'planLabel' => 'remediation-plan',
            'approved' => [
                'arch-001' => [
                    'approvedAt' => '2026-02-23T10:00:00+00:00',
                    'approvedBy' => 'rodri',
                ],
            ],
        ]);

        $useCase = new GetRemediationProposalStatus($this->store);
        $status = $useCase->execute('remediation-plan');

        $this->assertSame(1, $status['approvedCount']);
        $this->assertSame(1, $status['pendingCount']);
        $this->assertTrue($status['proposals'][0]['approved']);
        $this->assertSame('rodri', $status['proposals'][0]['approvedBy']);
        $this->assertFalse($status['proposals'][1]['approved']);
    }

    private function savePlan(string $label): void
    {
        $this->store->save($label, [
            'type' => 'remediation_proposals',
            'report' => [
                'generatedAt' => '2026-02-23T00:00:00+00:00',
                'total' => 2,
                'byCategory' => ['architecture' => 1, 'hotspot' => 1],
                'proposals' => [
                    [
                        'id' => 'arch-001',
                        'category' => 'architecture',
                        'priority' => 'P1',
                        'title' => 'Break forbidden dependency',
                        'target' => 'A -> B',
                        'requiresApproval' => true,
                    ],
                    [
                        'id' => 'hotspot-001',
                        'category' => 'hotspot',
                        'priority' => 'P2',
                        'title' => 'Decompose hotspot node',
                        'target' => 'Foo::bar',
                        'requiresApproval' => true,
                    ],
                ],
            ],
        ]);
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

