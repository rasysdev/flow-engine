<?php

namespace FlowEngine\AI\Context;

final class HotspotContext
{
    /**
     * @param int $totalMethods
     * @param float $avgComplexity
     * @param int $maxComplexity
     * @param array<string, int> $byLevel
     * @param array<int, array<string, mixed>> $complexMethods
     */
    public function __construct(
        public readonly int $totalMethods,
        public readonly float $avgComplexity,
        public readonly int $maxComplexity,
        public readonly array $byLevel,
        public readonly array $complexMethods
    ) {
    }
}
