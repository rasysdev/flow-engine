<?php

namespace Tests\Application\UseCase;

use PHPUnit\Framework\TestCase;
use FlowEngine\Application\UseCase\GetNodeById;
use FlowEngine\Domain\Flow\Node;
use Tests\Support\InMemoryFlowRepository;
final class GetNodeByIdTest extends TestCase
{
    public function test_it_returns_node_when_id_exists(): void
    {
        $repository = new InMemoryFlowRepository([
            new Node('A', 'x', 'file.php', 10),
            new Node('B', 'y', 'file.php', 10),
        ]);

        $useCase = new GetNodeById($repository);

        $node = $useCase->execute('A::x');

        $this->assertNotNull($node);
        $this->assertSame('A::x', $node->id());
    }

    public function test_it_returns_null_when_node_does_not_exist(): void
    {
        $repository = new InMemoryFlowRepository([
            new Node('A', 'x', 'file.php', null)
        ]);

        $useCase = new GetNodeById($repository);

        $node = $useCase->execute('C::z');

        $this->assertNull($node);
    }

}
