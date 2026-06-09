<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\Domain\Execution\ExecutionContext;
use FlowEngine\Domain\Execution\ExecutionResult;
use FlowEngine\Domain\Flow\ContextualNodeInvoker;
use FlowEngine\Domain\Flow\Node;
use FlowEngine\Domain\Flow\NodeInvoker;
use LogicException;

final class ExecuteNode
{
    public function __construct(
        private NodeInvoker $invoker
    ) {
    }

    public function execute(Node $node, array $inputs, ?ExecutionContext $context = null): ExecutionResult
    {
        if ($context !== null) {
            if (!$this->invoker instanceof ContextualNodeInvoker) {
                throw new LogicException('Node invoker does not support explicit execution context.');
            }

            return $this->invoker->invokeWithContext($node, $inputs, $context);
        }

        return $this->invoker->invoke($node, $inputs);
    }
}
