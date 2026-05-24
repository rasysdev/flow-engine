<?php

namespace FlowEngine\AI\Context;

/**
 * Readonly context for refactor plan generation.
 *
 * Aggregates data from NodeImpactReportDTO, SafetyAssessmentDTO, and ChangeRiskDTO
 * for AI-powered refactoring plan generation.
 */
final class RefactorPlanContext
{
    /**
     * @param string $nodeId Target node identifier
     * @param string[] $upstream Nodes that call this node
     * @param string[] $downstream Nodes this node calls
     * @param int $blastRadius Transitive impact count
     * @param int $fanIn Number of callers
     * @param int $fanOut Number of dependencies
     * @param int $complexityScore 0-100 complexity score
     * @param string $riskLevel LOW/MEDIUM/HIGH/CRITICAL
     * @param int $riskScore 0-100 normalized risk score
     * @param array<int, array{name: string, weight: float, value: float, contribution: float}> $riskFactors
     * @param array<int, array<string, mixed>> $cyclesInvolved Cycles containing this node
     * @param array<int, array<string, mixed>> $violationsInvolved Violations involving this node
     * @param array<int, array<string, mixed>> $cyclesAffected Cycles affected by refactoring
     * @param array<int, array<string, mixed>> $violationsAffected Violations affected by refactoring
     * @param string[] $potentialOrphans Downstream nodes that could become orphans
     * @param int $affectedNodeCount Total nodes affected by refactoring
     */
    public function __construct(
        public readonly string $nodeId,
        public readonly array $upstream,
        public readonly array $downstream,
        public readonly int $blastRadius,
        public readonly int $fanIn,
        public readonly int $fanOut,
        public readonly int $complexityScore,
        public readonly string $riskLevel,
        public readonly int $riskScore,
        public readonly array $riskFactors,
        public readonly array $cyclesInvolved,
        public readonly array $violationsInvolved,
        public readonly array $cyclesAffected,
        public readonly array $violationsAffected,
        public readonly array $potentialOrphans,
        public readonly int $affectedNodeCount
    ) {
    }
}
