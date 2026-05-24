<?php

namespace FlowEngine\Application\DTO;

/**
 * Pre-refactor safety report for a node.
 *
 * @api
 */
final readonly class SafetyAssessmentDTO
{
    /**
     * @param string $nodeId Target node identifier
     * @param int $affectedNodes Total nodes affected by refactoring
     * @param array<int, array<string, mixed>> $cyclesAffected Cycles involving affected nodes
     * @param array<int, array<string, mixed>> $violationsAffected Violations involving affected nodes
     * @param string[] $potentialOrphans Downstream nodes that could become orphans
     * @param string $overallRisk LOW/MEDIUM/HIGH/CRITICAL
     * @param string[] $recommendations Safety recommendations
     */
    public function __construct(
        public string $nodeId,
        public int $affectedNodes,
        public array $cyclesAffected,
        public array $violationsAffected,
        public array $potentialOrphans,
        public string $overallRisk,
        public array $recommendations
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'nodeId' => $this->nodeId,
            'affectedNodes' => $this->affectedNodes,
            'cyclesAffected' => $this->cyclesAffected,
            'violationsAffected' => $this->violationsAffected,
            'potentialOrphans' => $this->potentialOrphans,
            'overallRisk' => $this->overallRisk,
            'recommendations' => $this->recommendations,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }
}
