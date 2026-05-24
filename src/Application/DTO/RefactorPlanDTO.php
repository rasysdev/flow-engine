<?php

namespace FlowEngine\Application\DTO;

/**
 * Complete AI-generated refactoring plan for a node.
 *
 * @api
 */
final readonly class RefactorPlanDTO
{
    /**
     * @param string $nodeId Target node identifier
     * @param string $detectionReason Why this node needs refactoring
     * @param string $overallRisk LOW/MEDIUM/HIGH/CRITICAL
     * @param int $riskScore 0-100 normalized risk score
     * @param array<int, array{name: string, weight: float, value: float, contribution: float}> $riskFactors
     * @param RefactorPrerequisiteDTO[] $prerequisites Blocking issues
     * @param RefactorStepDTO[] $steps Ordered refactoring actions
     * @param string[] $testingGuidance Testing recommendations
     * @param int $estimatedComplexity 1-10 complexity estimate
     * @param array<string, mixed> $metadata Additional data (tokensUsed, trivial, transport metadata, etc)
     */
    public function __construct(
        public string $nodeId,
        public string $detectionReason,
        public string $overallRisk,
        public int $riskScore,
        public array $riskFactors,
        public array $prerequisites,
        public array $steps,
        public array $testingGuidance,
        public int $estimatedComplexity,
        public array $metadata = []
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'nodeId' => $this->nodeId,
            'detectionReason' => $this->detectionReason,
            'overallRisk' => $this->overallRisk,
            'riskScore' => $this->riskScore,
            'riskFactors' => $this->riskFactors,
            'prerequisites' => array_map(fn($p) => $p->toArray(), $this->prerequisites),
            'steps' => array_map(fn($s) => $s->toArray(), $this->steps),
            'testingGuidance' => $this->testingGuidance,
            'estimatedComplexity' => $this->estimatedComplexity,
            'metadata' => $this->metadata,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }
}
