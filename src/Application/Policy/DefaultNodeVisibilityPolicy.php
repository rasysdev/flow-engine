<?php

namespace FlowEngine\Application\Policy;

use FlowEngine\Domain\Flow\Node;
use FlowEngine\Domain\Node\NodeVisibility;

final class DefaultNodeVisibilityPolicy implements NodeVisibilityPolicy
{
    public function visibility(Node $node): NodeVisibility
    {
        return new NodeVisibility(NodeVisibility::PUBLIC);
    }
}
