<?php

namespace FlowEngine\AI\Context;

final class ChangeImpactContext
{
    /**
     * @param string $nodeId Target node identifier
     * @param string[] $upstream Nodes that call this node
     * @param string[] $downstream Nodes this node calls
     * @param int $blastRadius Transitive impact count
     * @param int $fanIn Number of callers
     * @param int $fanOut Number of dependencies
     * @param string $riskLevel LOW/MEDIUM/HIGH/CRITICAL
     * @param int $complexityScore 0-100 complexity score
     * @param array<int, array<string, mixed>> $cyclesInvolved
     * @param array<int, array<string, mixed>> $violationsInvolved
     * @param array<string, mixed> $riskSummary
     */
    public function __construct(
        public readonly string $nodeId,
        public readonly array $upstream,
        public readonly array $downstream,
        public readonly int $blastRadius,
        public readonly int $fanIn,
        public readonly int $fanOut,
        public readonly string $riskLevel,
        public readonly int $complexityScore,
        public readonly array $cyclesInvolved,
        public readonly array $violationsInvolved,
        public readonly array $riskSummary
    ) {
    }
}
