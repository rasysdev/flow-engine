<?php

namespace FlowEngine\Application\DTO;

/**
 * Risk score + factors for changing a node.
 *
 * @api
 */
final readonly class ChangeRiskDTO
{
    /**
     * @param string $nodeId Target node identifier
     * @param int $score 0-100 normalized risk score
     * @param string $level LOW/MEDIUM/HIGH/CRITICAL
     * @param array<int, array{name: string, weight: float, value: float, contribution: float}> $factors
     * @param array<string, mixed> $metrics NodeMetrics->toArray()
     * @param array<string, mixed> $metadata Optional transport metadata
     */
    public function __construct(
        public string $nodeId,
        public int $score,
        public string $level,
        public array $factors,
        public array $metrics,
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
            'score' => $this->score,
            'level' => $this->level,
            'factors' => $this->factors,
            'metrics' => $this->metrics,
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
