<?php

namespace FlowEngine\AI\Context;

final class CycleContext
{
    /**
     * @param int $totalCycles
     * @param int $totalNodesInCycles
     * @param array<string, int> $bySeverity
     * @param int $largestCycle
     * @param array<int, array<string, mixed>> $cycles
     */
    public function __construct(
        public readonly int $totalCycles,
        public readonly int $totalNodesInCycles,
        public readonly array $bySeverity,
        public readonly int $largestCycle,
        public readonly array $cycles
    ) {
    }
}
