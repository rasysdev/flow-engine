<?php

namespace FlowEngine\Application\DTO;

/**
 * Complete impact profile for a single node.
 *
 * @api
 */
final readonly class NodeImpactReportDTO
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
     * @param array<int, array<string, mixed>> $cyclesInvolved Cycles containing this node
     * @param array<int, array<string, mixed>> $violationsInvolved Violations involving this node
     * @param array<string, mixed> $riskSummary RiskScore->toArray()
     * @param array<int, array<string, mixed>> $potentialBugs Bug entries from BugReportDTO (optional)
     * @param array<string, mixed> $metadata Optional transport metadata
     */
    public function __construct(
        public string $nodeId,
        public array $upstream,
        public array $downstream,
        public int $blastRadius,
        public int $fanIn,
        public int $fanOut,
        public string $riskLevel,
        public int $complexityScore,
        public array $cyclesInvolved,
        public array $violationsInvolved,
        public array $riskSummary,
        public array $potentialBugs = [],
        public array $metadata = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'nodeId' => $this->nodeId,
            'upstream' => $this->upstream,
            'downstream' => $this->downstream,
            'blastRadius' => $this->blastRadius,
            'fanIn' => $this->fanIn,
            'fanOut' => $this->fanOut,
            'riskLevel' => $this->riskLevel,
            'complexityScore' => $this->complexityScore,
            'cyclesInvolved' => $this->cyclesInvolved,
            'violationsInvolved' => $this->violationsInvolved,
            'riskSummary'   => $this->riskSummary,
            'potentialBugs' => $this->potentialBugs,
        ];

        if ($this->metadata !== []) {
            $payload['metadata'] = $this->metadata;
        }

        return $payload;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }
}
