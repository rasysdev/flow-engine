<?php

namespace FlowEngine\Infrastructure\Execution;

use FlowEngine\Domain\Contracts\NodeExecutor;
use FlowEngine\Domain\Flow\Node;

final class SandboxNodeExecutor implements NodeExecutor
{
    public function __construct(
        private string $projectPath
    ) {
    }

    public function execute(Node $node, array $arguments): array
    {
        require_once $this->projectPath . '/vendor/autoload.php';

        $class = $this->resolveFqn($node);

        $instance = new $class();

        $start = microtime(true);

        try {
            $result = $instance->{$node->method()}(...$arguments);
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }

        return [
            'status' => 'ok',
            'result' => $result,
            'time_ms' => round((microtime(true) - $start) * 1000, 2)
        ];
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
