<?php

namespace FlowEngine\Application\Visibility;

use FlowEngine\Application\Policy\CompositeNodeVisibilityPolicy;
use FlowEngine\Application\Policy\VisibilityResolutionReport;
use FlowEngine\Domain\Flow\Node;
use FlowEngine\Domain\Node\NodeVisibility;

final class NodeVisibilityResolver
{
    public function __construct(
        private CompositeNodeVisibilityPolicy $policy
    ) {
    }

    public function resolve(Node $node): ?NodeVisibility
    {
        return $this->policy->visibility($node);
    }

    public function report(): VisibilityResolutionReport
    {
        return $this->policy->report();
    }

    public function explain(Node $node): VisibilityExplanation
    {
        $this->resolve($node); // garante execução
        $builder = new VisibilityExplanationBuilder();

        return $builder->build(
            $node->id(),
            $this->report()
        );
    }
}
