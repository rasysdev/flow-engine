<?php

namespace Tests\Infrastructure\Execution;

use PHPUnit\Framework\TestCase;
use FlowEngine\Infrastructure\Execution\NodeExecutor;
use FlowEngine\Domain\Flow\Node;

final class NodeExecutorTest extends TestCase
{
    public function test_it_executes_method_and_returns_value(): void
    {
        $projectPath = __DIR__ . '/../../Fixtures/ExampleProject';

        $executor = new NodeExecutor($projectPath);

        $node = new Node(
            'Calculator',
            'sum',
            'file.php',
            10
        );


        $result = $executor->execute($node, [2, 3]);

        $this->assertSame(5, $result);
    }
}