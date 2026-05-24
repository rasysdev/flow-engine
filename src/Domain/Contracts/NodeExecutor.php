<?php

namespace FlowEngine\Domain\Contracts;

use FlowEngine\Domain\Flow\Node;

interface NodeExecutor
{
    public function execute(Node $node, array $inputs): mixed;
}
