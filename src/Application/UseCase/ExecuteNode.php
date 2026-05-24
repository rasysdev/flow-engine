<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\Domain\Execution\ExecutionResult;
use FlowEngine\Domain\Flow\Node;
use FlowEngine\Domain\Flow\NodeInvoker;

final class ExecuteNode
{
    public function __construct(
        private NodeInvoker $invoker
    ) {
    }

    public function execute(Node $node, array $inputs): ExecutionResult
    {
        return $this->invoker->invoke($node, $inputs);
    }
}
