<?php

namespace FlowEngine\Infrastructure\Execution;

use FlowEngine\Domain\Contracts\ProjectContext;
use FlowEngine\Domain\Contracts\NodeExecutor as NodeExecutorContract;
use FlowEngine\Domain\Flow\Node;

final class NodeExecutor implements NodeExecutorContract
{
    public function __construct(
        private ProjectContext $context
    ) {
    }

    public function execute(Node $node, array $args): mixed
    {
        $this->context->boot();

        $class = $this->resolveFqn($node);
        $instance = new $class();

        return $instance->{$node->method()}(...$args);
    }

    private function resolveFqn(Node $node): string
    {
        $code = file_get_contents($node->file());

        if (preg_match('/namespace\s+([^;]+);/', $code, $m)) {
            return $m[1] . '\\' . $node->class();
        }

        return $node->class();
    }
}
