<?php

namespace FlowEngine\Application\Policy;

use FlowEngine\Domain\Node\NodeVisibility;

interface VisibilityCompositionStrategy
{
    /**
     * @param VisibilityDecisionResult[] $results Ordered
     */
    public function compose(array $results): ?NodeVisibility;
}
