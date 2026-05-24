<?php

namespace FlowEngine\Application\Visibility;

use FlowEngine\Application\Policy\VisibilityDecision;
use FlowEngine\Application\Policy\VisibilityPolicySource;

final class VisibilityExplanationItem
{
    public function __construct(
        public readonly int $order,
        public readonly string $policyClass,
        public readonly VisibilityPolicySource $source,
        public readonly VisibilityDecision $decision
    ) {
    }
}
