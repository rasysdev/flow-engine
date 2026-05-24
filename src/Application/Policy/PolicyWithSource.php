<?php

namespace FlowEngine\Application\Policy;

use FlowEngine\Domain\Flow\Node;
use FlowEngine\Domain\Node\NodeVisibility;

final class PolicyWithSource implements NodeVisibilityPolicy
{
    public function __construct(
        private NodeVisibilityPolicy $policy,
        private VisibilityPolicySource $source
    ) {
    }

    public function visibility(Node $node): ?NodeVisibility
    {
        return $this->policy->visibility($node);
    }

    public function source(): VisibilityPolicySource
    {
        return $this->source;
    }

    public function policyClass(): string
    {
        return $this->policy::class;
    }
}
