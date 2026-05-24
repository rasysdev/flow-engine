<?php

namespace FlowEngine\Domain\Analysis;

final readonly class RiskScore
{
    /**
     * @param int $score 0-100 normalized risk score
     * @param string $level LOW/MEDIUM/HIGH/CRITICAL
     * @param array<int, array{name: string, weight: float, value: float, contribution: float}> $factors
     */
    public function __construct(
        public int $score,
        public string $level,
        public array $factors
    ) {
    }

    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'level' => $this->level,
            'factors' => $this->factors,
        ];
    }
}
