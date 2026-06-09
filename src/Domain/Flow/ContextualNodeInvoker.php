<?php

namespace FlowEngine\Domain\Flow;

use FlowEngine\Domain\Execution\ExecutionContext;

interface ContextualNodeInvoker extends NodeInvoker
{
    /**
     * Executa um Node usando um contexto explicito de execucao.
     *
     * @param Node $node
     * @param array<int, mixed> $args
     */
    public function invokeWithContext(Node $node, array $args, ExecutionContext $context): mixed;
}
