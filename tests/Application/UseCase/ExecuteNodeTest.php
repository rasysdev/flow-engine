<?php

namespace Tests\Application\UseCase;

use PHPUnit\Framework\TestCase;
use FlowEngine\Application\UseCase\ExecuteNode;
use FlowEngine\Domain\Flow\Node;
use FlowEngine\Domain\Flow\NodeInvoker;
use FlowEngine\Domain\Execution\ExecutionContext;
use FlowEngine\Domain\Execution\ExecutionResult;

final class ExecuteNodeTest extends TestCase
{
    /**
     * Garante que o use case apenas delega a execução
     * e retorna o ExecutionResult corretamente.
     */
    public function test_it_executes_node_and_returns_result(): void
    {
        $node = new Node('Calculator', 'sum', 'file.php', 10);

        $context = ExecutionContext::forNode($node->id());

        $executionResult = ExecutionResult::success(
            context: $context,
            nodeId: $node->id(),
            inputs: [1, 2],
            output: 3,
            durationMs: 1.0
        );

        $invoker = $this->createMock(NodeInvoker::class);
        $invoker
            ->expects($this->once())
            ->method('invoke')
            ->with($node, [1, 2])
            ->willReturn($executionResult);

        $useCase = new ExecuteNode($invoker);

        $result = $useCase->execute($node, [1, 2]);

        $this->assertSame($executionResult, $result);
        $this->assertTrue($result->isSuccess());
    }
}
