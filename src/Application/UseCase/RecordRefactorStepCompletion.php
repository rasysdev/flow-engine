<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\Application\DTO\RefactorProgressDTO;
use FlowEngine\Domain\Contracts\SnapshotStorePort;

final class RecordRefactorStepCompletion
{
    private const VALID_STATUSES = ['pending', 'done', 'skipped', 'failed'];

    public function __construct(
        private SnapshotStorePort $snapshotStore
    ) {
    }

    public function execute(string $planLabel, int $stepNumber, string $status): RefactorProgressDTO
    {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new \InvalidArgumentException(
                "Invalid status '{$status}'. Must be one of: " . implode(', ', self::VALID_STATUSES)
            );
        }

        // 1. Load original plan
        $planPayload = $this->snapshotStore->load($planLabel);

        if (($planPayload['type'] ?? '') !== 'refactor_plan') {
            throw new \RuntimeException("Snapshot '{$planLabel}' is not a refactor plan.");
        }

        $planData = $planPayload['plan'] ?? [];
        $nodeId = $planData['nodeId'] ?? '';
        $planSteps = $planData['steps'] ?? [];

        $progressLabel = $planLabel . '-progress';

        // 2. Load or create progress record
        $progressData = $this->loadOrInitProgress($progressLabel, $planLabel, $nodeId, $planSteps);

        // 3. Update the step status
        $steps = $progressData['steps'];
        $found = false;

        foreach ($steps as &$step) {
            if ($step['order'] === $stepNumber) {
                $step['status'] = $status;
                $step['completedAt'] = in_array($status, ['done', 'skipped', 'failed'], true)
                    ? date('Y-m-d H:i:s')
                    : null;
                $found = true;
                break;
            }
        }
        unset($step);

        if (!$found) {
            throw new \RuntimeException("Step {$stepNumber} not found in plan '{$planLabel}'.");
        }

        // 4. Compute totals
        $completedSteps = count(array_filter($steps, fn(array $s) => in_array($s['status'], ['done', 'skipped'], true)));

        $currentStep = null;
        foreach ($steps as $s) {
            if ($s['status'] === 'pending') {
                $currentStep = $s['order'];
                break;
            }
        }

        $savedAt = date('Y-m-d H:i:s');

        // 5. Persist progress
        $this->snapshotStore->save($progressLabel, [
            'type' => 'refactor_progress',
            'planLabel' => $planLabel,
            'nodeId' => $nodeId,
            'totalSteps' => count($steps),
            'completedSteps' => $completedSteps,
            'currentStep' => $currentStep,
            'steps' => $steps,
            'savedAt' => $savedAt,
        ]);

        return new RefactorProgressDTO(
            planLabel: $planLabel,
            nodeId: $nodeId,
            totalSteps: count($steps),
            completedSteps: $completedSteps,
            currentStep: $currentStep,
            steps: $steps,
            savedAt: $savedAt
        );
    }

    private function loadOrInitProgress(string $progressLabel, string $planLabel, string $nodeId, array $planSteps): array
    {
        if ($this->snapshotStore->exists($progressLabel)) {
            $existing = $this->snapshotStore->load($progressLabel);
            if (($existing['type'] ?? '') === 'refactor_progress') {
                return $existing;
            }
        }

        // Initialize from plan steps
        $steps = array_map(fn(array $s) => [
            'order' => $s['order'],
            'action' => $s['action'],
            'target' => $s['target'],
            'status' => 'pending',
            'completedAt' => null,
        ], $planSteps);

        // Sort by order
        usort($steps, fn(array $a, array $b) => $a['order'] <=> $b['order']);

        return [
            'type' => 'refactor_progress',
            'planLabel' => $planLabel,
            'nodeId' => $nodeId,
            'steps' => $steps,
        ];
    }
}
