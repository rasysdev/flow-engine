<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\Domain\Contracts\SnapshotStorePort;

final class GetRemediationProposalStatus
{
    public function __construct(
        private SnapshotStorePort $snapshotStore
    ) {
    }

    /**
     * @return array{
     *   planLabel: string,
     *   generatedAt: string,
     *   total: int,
     *   approvedCount: int,
     *   pendingCount: int,
     *   proposals: array<int, array<string, mixed>>
     * }
     */
    public function execute(string $planLabel): array
    {
        $planPayload = $this->snapshotStore->load($planLabel);

        if (($planPayload['type'] ?? '') !== 'remediation_proposals') {
            throw new \RuntimeException("Snapshot '{$planLabel}' is not a remediation proposals report.");
        }

        $report = $planPayload['report'] ?? [];
        $proposals = is_array($report['proposals'] ?? null) ? $report['proposals'] : [];

        $approvalLabel = $planLabel . '-approvals';
        $approved = [];

        if ($this->snapshotStore->exists($approvalLabel)) {
            $approvalPayload = $this->snapshotStore->load($approvalLabel);
            if (($approvalPayload['type'] ?? '') !== 'remediation_approvals') {
                throw new \RuntimeException("Snapshot '{$approvalLabel}' is not a remediation approvals store.");
            }

            $approved = is_array($approvalPayload['approved'] ?? null) ? $approvalPayload['approved'] : [];
        }

        $statusItems = [];
        $approvedCount = 0;

        foreach ($proposals as $proposal) {
            $id = (string) ($proposal['id'] ?? '');
            $isApproved = $id !== '' && array_key_exists($id, $approved);
            if ($isApproved) {
                $approvedCount++;
            }

            $statusItems[] = [
                'id' => $id,
                'category' => (string) ($proposal['category'] ?? 'unknown'),
                'priority' => (string) ($proposal['priority'] ?? 'P3'),
                'title' => (string) ($proposal['title'] ?? ''),
                'target' => (string) ($proposal['target'] ?? ''),
                'requiresApproval' => (bool) ($proposal['requiresApproval'] ?? true),
                'approved' => $isApproved,
                'approvedBy' => $isApproved ? (string) ($approved[$id]['approvedBy'] ?? 'human') : null,
                'approvedAt' => $isApproved ? (string) ($approved[$id]['approvedAt'] ?? null) : null,
            ];
        }

        $total = count($statusItems);

        return [
            'planLabel' => $planLabel,
            'generatedAt' => (string) ($report['generatedAt'] ?? ''),
            'total' => $total,
            'approvedCount' => $approvedCount,
            'pendingCount' => max(0, $total - $approvedCount),
            'proposals' => $statusItems,
        ];
    }
}
