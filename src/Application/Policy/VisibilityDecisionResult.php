<?php

namespace FlowEngine\Application\Policy;

use FlowEngine\Domain\Node\NodeVisibility;

final class VisibilityDecisionResult
{
    public function __construct(
        public readonly string $policyClass,
        public readonly VisibilityPolicySource $source,
        public readonly VisibilityDecision $decision,
        public readonly ?NodeVisibility $visibility
    ) {
    }
}
