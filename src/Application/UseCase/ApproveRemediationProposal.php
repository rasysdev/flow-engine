<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\Domain\Contracts\SnapshotStorePort;

final class ApproveRemediationProposal
{
    public function __construct(
        private SnapshotStorePort $snapshotStore
    ) {
    }

    /**
     * @return array{
     *   planLabel: string,
     *   proposalId: string,
     *   approvedBy: string,
     *   approvedAt: string,
     *   alreadyApproved: bool,
     *   total: int,
     *   approvedCount: int,
     *   pendingCount: int
     * }
     */
    public function execute(string $planLabel, string $proposalId, ?string $approvedBy = null): array
    {
        $planPayload = $this->snapshotStore->load($planLabel);

        if (($planPayload['type'] ?? '') !== 'remediation_proposals') {
            throw new \RuntimeException("Snapshot '{$planLabel}' is not a remediation proposals report.");
        }

        $report = $planPayload['report'] ?? [];
        $proposals = is_array($report['proposals'] ?? null) ? $report['proposals'] : [];

        $proposal = $this->findProposalById($proposals, $proposalId);
        if ($proposal === null) {
            throw new \RuntimeException("Proposal '{$proposalId}' not found in '{$planLabel}'.");
        }

        if (($proposal['requiresApproval'] ?? true) !== true) {
            throw new \RuntimeException("Proposal '{$proposalId}' does not require approval.");
        }

        $approvalLabel = $planLabel . '-approvals';
        $approvalPayload = $this->snapshotStore->exists($approvalLabel)
            ? $this->snapshotStore->load($approvalLabel)
            : [
                'type' => 'remediation_approvals',
                'planLabel' => $planLabel,
                'approved' => [],
            ];

        if (($approvalPayload['type'] ?? '') !== 'remediation_approvals') {
            throw new \RuntimeException("Snapshot '{$approvalLabel}' is not a remediation approvals store.");
        }

        $approved = is_array($approvalPayload['approved'] ?? null) ? $approvalPayload['approved'] : [];
        $alreadyApproved = array_key_exists($proposalId, $approved);
        $approvedAt = $alreadyApproved ? (string) ($approved[$proposalId]['approvedAt'] ?? '') : date(\DateTimeInterface::ATOM);
        $approvedByValue = $alreadyApproved
            ? (string) ($approved[$proposalId]['approvedBy'] ?? 'human')
            : (trim((string) $approvedBy) !== '' ? trim((string) $approvedBy) : 'human');

        if (!$alreadyApproved) {
            $approved[$proposalId] = [
                'approvedAt' => $approvedAt,
                'approvedBy' => $approvedByValue,
            ];

            $this->snapshotStore->save($approvalLabel, [
                'type' => 'remediation_approvals',
                'planLabel' => $planLabel,
                'approved' => $approved,
                'savedAt' => date('Y-m-d H:i:s'),
            ]);
        }

        $total = count($proposals);
        $approvedCount = count($approved);
        $pendingCount = max(0, $total - $approvedCount);

        return [
            'planLabel' => $planLabel,
            'proposalId' => $proposalId,
            'approvedBy' => $approvedByValue,
            'approvedAt' => $approvedAt,
            'alreadyApproved' => $alreadyApproved,
            'total' => $total,
            'approvedCount' => $approvedCount,
            'pendingCount' => $pendingCount,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $proposals
     * @return array<string, mixed>|null
     */
    private function findProposalById(array $proposals, string $proposalId): ?array
    {
        foreach ($proposals as $proposal) {
            if (($proposal['id'] ?? null) === $proposalId) {
                return $proposal;
            }
        }

        return null;
    }
}
