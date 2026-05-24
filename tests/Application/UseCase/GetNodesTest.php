<?php

namespace Tests\Application\UseCase;

use FlowEngine\Application\DTO\NodeDTO;
use FlowEngine\Application\UseCase\GetNodes;
use FlowEngine\Domain\Flow\Node;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryFlowRepository;

/**
 * @covers \FlowEngine\Application\UseCase\GetNodes
 */
final class GetNodesTest extends TestCase
{
    public function test_it_returns_nodes_from_repository(): void
    {
        $repository = new InMemoryFlowRepository([
            new Node('A', 'x', __FILE__, null),
            new Node('B', 'y', __FILE__, null),
            new Node('C', 'z', __FILE__, null),
        ]);

        $useCase = new GetNodes($repository);
        $nodes = $useCase->execute();

        $this->assertCount(3, $nodes);
        $this->assertContainsOnlyInstancesOf(NodeDTO::class, $nodes);
    }
}
