<?php

namespace FlowEngine\Application\Visibility;

use FlowEngine\Application\Policy\VisibilityDecision;

final class VisibilityExplanation
{
    /**
     * @param VisibilityExplanationItem[] $items
     */
    public function __construct(
        public readonly string $nodeId,
        public readonly VisibilityDecision $finalDecision,
        public readonly array $items
    ) {
    }
}
