<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\Application\DTO\RefactorPlanDTO;
use FlowEngine\Domain\Contracts\SnapshotStorePort;

final class SaveRefactorPlan
{
    public function __construct(
        private SnapshotStorePort $store
    ) {
    }

    public function execute(string $label, RefactorPlanDTO $plan): void
    {
        $payload = [
            'type' => 'refactor_plan',
            'plan' => $plan->toArray(),
            'savedAt' => date('Y-m-d H:i:s'),
        ];

        $this->store->save($label, $payload);
    }
}
