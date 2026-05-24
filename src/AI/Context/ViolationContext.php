<?php

namespace FlowEngine\AI\Context;

final class ViolationContext
{
    /**
     * @param bool $isClean
     * @param int $totalViolations
     * @param array<string, int> $bySeverity
     * @param array<string, int> $byType
     * @param array<string, int> $layerDistribution
     * @param array<int, array<string, mixed>> $violations
     */
    public function __construct(
        public readonly bool $isClean,
        public readonly int $totalViolations,
        public readonly array $bySeverity,
        public readonly array $byType,
        public readonly array $layerDistribution,
        public readonly array $violations
    ) {
    }
}
