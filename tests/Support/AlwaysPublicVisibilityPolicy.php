<?php

namespace Tests\Support;

use FlowEngine\Application\Policy\NodeVisibilityPolicy;
use FlowEngine\Domain\Flow\Node;
use FlowEngine\Domain\Node\NodeVisibility;

final class AlwaysPublicVisibilityPolicy implements NodeVisibilityPolicy
{
    public function visibility(Node $node): NodeVisibility
    {
        return new NodeVisibility(NodeVisibility::PUBLIC);
    }
}
