<?php

namespace Tests\Application\UseCase;

use FlowEngine\Application\UseCase\ApproveRemediationProposal;
use FlowEngine\Infrastructure\Cache\SnapshotStore;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestProjectContext;

final class ApproveRemediationProposalTest extends TestCase
{
    private string $tempDir;
    private SnapshotStore $store;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/approve-remediation-test-' . uniqid('', true);
        mkdir($this->tempDir, 0777, true);
        $this->store = new SnapshotStore(new TestProjectContext($this->tempDir));
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    public function test_approves_proposal_and_persists_approval_state(): void
    {
        $this->savePlan('remediation-plan');
        $useCase = new ApproveRemediationProposal($this->store);

        $result = $useCase->execute('remediation-plan', 'arch-001', 'rodri');

        $this->assertSame('remediation-plan', $result['planLabel']);
        $this->assertSame('arch-001', $result['proposalId']);
        $this->assertSame('rodri', $result['approvedBy']);
        $this->assertFalse($result['alreadyApproved']);
        $this->assertSame(2, $result['total']);
        $this->assertSame(1, $result['approvedCount']);
        $this->assertSame(1, $result['pendingCount']);

        $approvalSnapshot = $this->store->load('remediation-plan-approvals');
        $this->assertSame('remediation_approvals', $approvalSnapshot['type']);
        $this->assertArrayHasKey('arch-001', $approvalSnapshot['approved']);
    }

    public function test_second_approval_is_idempotent(): void
    {
        $this->savePlan('remediation-plan');
        $useCase = new ApproveRemediationProposal($this->store);

        $first = $useCase->execute('remediation-plan', 'arch-001', 'rodri');
        $second = $useCase->execute('remediation-plan', 'arch-001', 'another-user');

        $this->assertFalse($first['alreadyApproved']);
        $this->assertTrue($second['alreadyApproved']);
        $this->assertSame('rodri', $second['approvedBy']);
        $this->assertSame($first['approvedAt'], $second['approvedAt']);
    }

    public function test_throws_when_proposal_does_not_exist(): void
    {
        $this->savePlan('remediation-plan');
        $useCase = new ApproveRemediationProposal($this->store);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Proposal 'missing-001' not found in 'remediation-plan'.");

        $useCase->execute('remediation-plan', 'missing-001');
    }

    public function test_throws_when_snapshot_is_not_remediation_proposals(): void
    {
        $this->store->save('not-a-plan', ['type' => 'refactor_plan', 'plan' => []]);
        $useCase = new ApproveRemediationProposal($this->store);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Snapshot 'not-a-plan' is not a remediation proposals report.");

        $useCase->execute('not-a-plan', 'arch-001');
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
                        'target' => 'A -> B',
                        'title' => 'Break forbidden dependency',
                        'reason' => 'Violation',
                        'actions' => ['x'],
                        'expectedImpact' => ['reduceArchitectureViolationsBy' => 1],
                        'requiresApproval' => true,
                    ],
                    [
                        'id' => 'hotspot-001',
                        'category' => 'hotspot',
                        'priority' => 'P2',
                        'target' => 'Foo::bar',
                        'title' => 'Decompose hotspot node',
                        'reason' => 'Hotspot',
                        'actions' => ['y'],
                        'expectedImpact' => ['reduceComplexityBy' => 2],
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

