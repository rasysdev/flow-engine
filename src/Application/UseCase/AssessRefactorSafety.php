<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\Application\DTO\SafetyAssessmentDTO;
use FlowEngine\Domain\Analysis\ArchitectureValidator;
use FlowEngine\Domain\Analysis\CycleDetector;
use FlowEngine\Domain\Analysis\MetricsAnalyzer;
use FlowEngine\Domain\Contracts\FlowRepository;

final class AssessRefactorSafety
{
    public function __construct(
        private AnalyzeImpact $analyzeImpact,
        private FlowRepository $repository
    ) {
    }

    public function execute(string $nodeId): SafetyAssessmentDTO
    {
        $impact = $this->analyzeImpact->execute($nodeId);
        $upstream = $impact['impact']['upstream'];
        $downstream = $impact['impact']['downstream'];
        $allAffected = array_unique(array_merge($upstream, $downstream));

        $flow = $this->repository->getFlow();

        // Cycles involving target or affected nodes
        $cycleDetector = new CycleDetector($flow);
        $allCycles = $cycleDetector->detectCycles();
        $affectedNodesWithTarget = array_merge($allAffected, [$nodeId]);
        $cyclesAffected = array_values(array_filter(
            $allCycles,
            function (array $cycle) use ($affectedNodesWithTarget) {
                foreach ($cycle['nodes'] as $node) {
                    if (in_array($node, $affectedNodesWithTarget, true)) {
                        return true;
                    }
                }
                return false;
            }
        ));

        // Violations involving affected nodes
        $archValidator = new ArchitectureValidator($flow);
        $allViolations = $archValidator->detectViolations();
        $violationsAffected = array_values(array_filter(
            $allViolations,
            function (array $v) use ($affectedNodesWithTarget) {
                return in_array($v['from'], $affectedNodesWithTarget, true)
                    || in_array($v['to'], $affectedNodesWithTarget, true);
            }
        ));

        // Potential orphans: downstream nodes that would become orphans if target removed
        $metricsAnalyzer = new MetricsAnalyzer($flow);
        $potentialOrphans = [];
        foreach ($downstream as $downstreamNode) {
            $downstreamMetrics = $metricsAnalyzer->metricsFor($downstreamNode);
            // If this downstream node has only 1 caller (the target), it would become orphan
            if ($downstreamMetrics->fanIn <= 1) {
                $potentialOrphans[] = $downstreamNode;
            }
        }

        // Calculate overall risk
        $affectedCount = count($allAffected);
        $overallRisk = $this->calculateOverallRisk(
            $affectedCount,
            count($cyclesAffected),
            count($violationsAffected),
            count($potentialOrphans)
        );

        // Generate recommendations
        $recommendations = $this->generateRecommendations(
            $nodeId,
            $affectedCount,
            $cyclesAffected,
            $violationsAffected,
            $potentialOrphans
        );

        return new SafetyAssessmentDTO(
            nodeId: $nodeId,
            affectedNodes: $affectedCount,
            cyclesAffected: $cyclesAffected,
            violationsAffected: $violationsAffected,
            potentialOrphans: $potentialOrphans,
            overallRisk: $overallRisk,
            recommendations: $recommendations
        );
    }

    private function calculateOverallRisk(
        int $affectedCount,
        int $cycleCount,
        int $violationCount,
        int $orphanCount
    ): string {
        $score = $affectedCount * 2 + $cycleCount * 10 + $violationCount * 8 + $orphanCount * 3;

        if ($score >= 50) {
            return 'CRITICAL';
        }

        if ($score >= 30) {
            return 'HIGH';
        }

        if ($score >= 10) {
            return 'MEDIUM';
        }

        return 'LOW';
    }

    /**
     * @return string[]
     */
    private function generateRecommendations(
        string $nodeId,
        int $affectedCount,
        array $cyclesAffected,
        array $violationsAffected,
        array $potentialOrphans
    ): array {
        $recommendations = [];

        if ($affectedCount === 0) {
            $recommendations[] = 'No dependencies detected. Safe to refactor.';
            return $recommendations;
        }

        if ($affectedCount > 10) {
            $recommendations[] = "High blast radius ({$affectedCount} affected nodes). Consider incremental refactoring.";
        }

        if (count($cyclesAffected) > 0) {
            $recommendations[] = 'Break dependency cycles before refactoring to reduce cascading risk.';
        }

        if (count($violationsAffected) > 0) {
            $recommendations[] = 'Resolve architecture violations first to avoid propagating structural issues.';
        }

        if (count($potentialOrphans) > 0) {
            $orphanList = implode(', ', array_slice($potentialOrphans, 0, 3));
            $recommendations[] = "Potential orphans after refactoring: {$orphanList}. Ensure these nodes have alternative callers.";
        }

        $recommendations[] = 'Add or verify tests for downstream dependencies before refactoring.';

        return $recommendations;
    }
}
