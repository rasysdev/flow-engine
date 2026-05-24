<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\Application\DTO\RemediationProposalReportDTO;
use FlowEngine\Domain\Contracts\SnapshotStorePort;

final class SaveRemediationProposals
{
    public function __construct(
        private SnapshotStorePort $store
    ) {
    }

    public function execute(string $label, RemediationProposalReportDTO $report): void
    {
        $this->store->save($label, [
            'type' => 'remediation_proposals',
            'report' => $report->toArray(),
            'savedAt' => date('Y-m-d H:i:s'),
        ]);
    }
}
