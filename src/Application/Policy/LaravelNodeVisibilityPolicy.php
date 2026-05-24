<?php

namespace FlowEngine\Application\Policy;

use FlowEngine\Domain\Flow\Node;
use FlowEngine\Domain\Node\NodeVisibility;

final class LaravelNodeVisibilityPolicy implements NodeVisibilityPolicy
{
    public function visibility(Node $node): ?NodeVisibility
    {
        if (str_starts_with($node->class(), 'Illuminate\\')) {
            return new NodeVisibility(NodeVisibility::HIDDEN);
        }

        return null; // não decide → próxima policy
    }
}
