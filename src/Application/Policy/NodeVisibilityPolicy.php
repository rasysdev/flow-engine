<?php

namespace FlowEngine\Application\Policy;

use FlowEngine\Domain\Flow\Node;

namespace FlowEngine\Application\Policy;

use FlowEngine\Domain\Flow\Node;
use FlowEngine\Domain\Node\NodeVisibility;

/**
 * A policy MAY decide visibility or abstain (return null).
 * Composition and conflict resolution are handled externally.
 */
interface NodeVisibilityPolicy
{
    public function visibility(Node $node): ?NodeVisibility;
}
