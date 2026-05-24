<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\Application\DTO\RefactorValidationDTO;
use FlowEngine\Domain\Contracts\SnapshotStorePort;

final class ValidateRefactorStep
{
    public function __construct(
        private AssessNodeImpact $assessNodeImpact,
        private SnapshotStorePort $snapshotStore
    ) {
    }

    public function execute(string $planLabel, int $stepNumber): RefactorValidationDTO
    {
        // 1. Load saved plan
        $payload = $this->snapshotStore->load($planLabel);

        if (($payload['type'] ?? '') !== 'refactor_plan') {
            throw new \RuntimeException("Snapshot '{$planLabel}' is not a refactor plan.");
        }

        $planData = $payload['plan'] ?? [];
        $nodeId = $planData['nodeId'] ?? '';
        $steps = $planData['steps'] ?? [];

        // 2. Find the requested step
        $step = $this->findStep($steps, $stepNumber);

        if ($step === null) {
            throw new \RuntimeException("Step {$stepNumber} not found in plan '{$planLabel}'.");
        }

        // 3. Re-analyse the node to get current metrics
        $impact = $this->assessNodeImpact->execute($nodeId);

        $currentMetrics = [
            'fanIn' => $impact->fanIn,
            'fanOut' => $impact->fanOut,
            'blastRadius' => $impact->blastRadius,
        ];

        // 4. Extract baseline metrics for the step's target node from plan context
        // The plan stores the top-level node's metrics; use them as baseline
        $baselineMetrics = $this->extractBaselineMetrics($planData, $step);

        // 5. Validate by step type
        $rationale = strtolower($step['rationale'] ?? '');
        $action = strtolower($step['action'] ?? '');

        if ($this->mentionsCycles($rationale, $action)) {
            return $this->validateCycleStep($nodeId, $stepNumber, $step, $impact, $currentMetrics, $baselineMetrics);
        }

        if ($this->mentionsLayerViolation($rationale, $action)) {
            return $this->validateViolationStep($nodeId, $stepNumber, $step, $impact, $currentMetrics, $baselineMetrics);
        }

        return $this->validateDefaultStep($nodeId, $stepNumber, $step, $impact, $currentMetrics, $baselineMetrics);
    }

    private function findStep(array $steps, int $stepNumber): ?array
    {
        foreach ($steps as $step) {
            if (($step['order'] ?? null) === $stepNumber) {
                return $step;
            }
        }

        return null;
    }

    private function extractBaselineMetrics(array $planData, array $step): array
    {
        // Plan stores risk factors; we reconstruct approximate baseline from stored metadata
        // The plan itself was generated from the node's metrics at that point in time.
        // We store what was available: fanIn/fanOut/blastRadius are in the impact context.
        // Best effort: use planData metadata if present, otherwise return zeros.
        $meta = $planData['metadata'] ?? [];
        return [
            'fanIn' => (int) ($meta['baselineFanIn'] ?? 0),
            'fanOut' => (int) ($meta['baselineFanOut'] ?? 0),
            'blastRadius' => (int) ($meta['baselineBlastRadius'] ?? 0),
        ];
    }

    private function mentionsCycles(string $rationale, string $action): bool
    {
        return str_contains($rationale, 'cycle') || str_contains($action, 'cycle')
            || str_contains($rationale, 'circular') || str_contains($action, 'circular');
    }

    private function mentionsLayerViolation(string $rationale, string $action): bool
    {
        return str_contains($rationale, 'layer') || str_contains($action, 'layer')
            || str_contains($rationale, 'violation') || str_contains($action, 'violation')
            || str_contains($rationale, 'architecture') || str_contains($action, 'architecture');
    }

    private function validateCycleStep(
        string $nodeId,
        int $stepNumber,
        array $step,
        \FlowEngine\Application\DTO\NodeImpactReportDTO $impact,
        array $currentMetrics,
        array $baselineMetrics
    ): RefactorValidationDTO {
        $cyclesNow = count($impact->cyclesInvolved);
        $passed = $cyclesNow === 0;
        $findings = [];
        $recommendation = '';

        if ($passed) {
            $findings[] = "No cycles detected involving `{$nodeId}`. Step appears successfully applied.";
            $recommendation = 'Proceed to the next step.';
        } else {
            $findings[] = "{$cyclesNow} cycle(s) still detected involving `{$nodeId}`.";
            $findings[] = 'The cycle-breaking refactoring may not yet be complete.';
            $recommendation = 'Review remaining circular dependencies and ensure the interface or inversion was applied correctly.';
        }

        return new RefactorValidationDTO(
            nodeId: $nodeId,
            stepOrder: $stepNumber,
            passed: $passed,
            findings: $findings,
            currentMetrics: $currentMetrics,
            baselineMetrics: $baselineMetrics,
            recommendation: $recommendation
        );
    }

    private function validateViolationStep(
        string $nodeId,
        int $stepNumber,
        array $step,
        \FlowEngine\Application\DTO\NodeImpactReportDTO $impact,
        array $currentMetrics,
        array $baselineMetrics
    ): RefactorValidationDTO {
        $violationsNow = count($impact->violationsInvolved);
        $passed = $violationsNow === 0;
        $findings = [];
        $recommendation = '';

        if ($passed) {
            $findings[] = "No architecture violations detected involving `{$nodeId}`. Step appears successfully applied.";
            $recommendation = 'Proceed to the next step.';
        } else {
            $findings[] = "{$violationsNow} violation(s) still detected involving `{$nodeId}`.";
            $findings[] = 'The layer/architecture fix may not yet be complete.';
            $recommendation = 'Ensure the dependency was removed or inverted as planned. Check layer assignments in flow-engine.json.';
        }

        return new RefactorValidationDTO(
            nodeId: $nodeId,
            stepOrder: $stepNumber,
            passed: $passed,
            findings: $findings,
            currentMetrics: $currentMetrics,
            baselineMetrics: $baselineMetrics,
            recommendation: $recommendation
        );
    }

    private function validateDefaultStep(
        string $nodeId,
        int $stepNumber,
        array $step,
        \FlowEngine\Application\DTO\NodeImpactReportDTO $impact,
        array $currentMetrics,
        array $baselineMetrics
    ): RefactorValidationDTO {
        $findings = [];
        $passed = true;

        // Compare blastRadius and fanIn; improvement means ≤ baseline (or baseline unknown)
        $baselineBlast = $baselineMetrics['blastRadius'];
        $baselineFanIn = $baselineMetrics['fanIn'];

        if ($baselineBlast > 0 && $currentMetrics['blastRadius'] > $baselineBlast) {
            $passed = false;
            $findings[] = "Blast radius increased from {$baselineBlast} to {$currentMetrics['blastRadius']} — step may have introduced new dependencies.";
        } elseif ($baselineBlast > 0) {
            $findings[] = "Blast radius: {$currentMetrics['blastRadius']} (baseline: {$baselineBlast}).";
        } else {
            $findings[] = "Blast radius: {$currentMetrics['blastRadius']} (no baseline stored — run plan with --save to capture metrics).";
        }

        if ($baselineFanIn > 0 && $currentMetrics['fanIn'] > $baselineFanIn) {
            $passed = false;
            $findings[] = "Fan-in increased from {$baselineFanIn} to {$currentMetrics['fanIn']} — step may not have reduced coupling as intended.";
        } elseif ($baselineFanIn > 0) {
            $findings[] = "Fan-in: {$currentMetrics['fanIn']} (baseline: {$baselineFanIn}).";
        }

        $recommendation = $passed
            ? 'Metrics look stable or improved. Proceed to the next step.'
            : 'Metrics regressed. Review your changes and ensure no new dependencies were introduced.';

        return new RefactorValidationDTO(
            nodeId: $nodeId,
            stepOrder: $stepNumber,
            passed: $passed,
            findings: $findings,
            currentMetrics: $currentMetrics,
            baselineMetrics: $baselineMetrics,
            recommendation: $recommendation
        );
    }
}
