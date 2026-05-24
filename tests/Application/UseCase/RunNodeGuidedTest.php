<?php

namespace Tests\Application\UseCase;

use FlowEngine\Domain\Execution\ExecutionContext;
use FlowEngine\Domain\Execution\ExecutionResult;
use PHPUnit\Framework\TestCase;
use FlowEngine\Application\UseCase\RunNodeGuided;
use FlowEngine\Application\UseCase\GetNodeById;
use FlowEngine\Application\UseCase\ExecuteNode;
use FlowEngine\Application\UseCase\ResolveGuidedArguments;
use FlowEngine\Application\Port\NodeInputsProvider;
use FlowEngine\Domain\Flow\Node;
use FlowEngine\Domain\Contracts\FlowRepository;
use FlowEngine\Domain\Flow\NodeInvoker;
use FlowEngine\Application\DTO\NodeInputs;
use FlowEngine\Application\DTO\NodeInputDefinition;

final class RunNodeGuidedTest extends TestCase
{
    public function test_it_runs_node_in_guided_mode(): void
    {
        $node = new Node(
            'Calculator',
            'sum',
            'Calculator.php',
            10
        );

        $repository = $this->createMock(FlowRepository::class);
        $repository
            ->method('findNode')
            ->with('Calculator::sum')
            ->willReturn($node);

        $getNodeInputs = $this->createMock(NodeInputsProvider::class);
        $getNodeInputs
            ->method('execute')
            ->with($node)
            ->willReturn(
                new NodeInputs(
                    [
                        new NodeInputDefinition('a', 'int', true, null),
                        new NodeInputDefinition('b', 'int', true, null),
                    ],
                    'int'
                )
            );

        $context = ExecutionContext::forNode('Calculator::sum');

        $executionResult = ExecutionResult::success(
            context: $context,
            nodeId: 'Calculator::sum',
            inputs: [1, 2],
            output: 3,
            durationMs: 1.0
        );

        $invoker = $this->createMock(NodeInvoker::class);
        $invoker
            ->method('invoke')
            ->willReturn($executionResult);

        $executeNode = new ExecuteNode($invoker);

        $useCase = new RunNodeGuided(
            new GetNodeById($repository),
            $getNodeInputs,
            new ResolveGuidedArguments(),
            $executeNode
        );

        $result = $useCase->execute('Calculator::sum', [1, 2]);

        $this->assertSame('Calculator::sum', $result->nodeId);
        $this->assertSame(3, $result->result->output);
        $this->assertCount(2, $result->inputs);
    }
}
