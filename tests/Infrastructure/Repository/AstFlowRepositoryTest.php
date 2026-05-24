<?php

namespace Tests\Infrastructure\Repository;

use FlowEngine\Domain\Flow\DefaultNodeFactory;
use PHPUnit\Framework\TestCase;
use FlowEngine\Infrastructure\Repository\AstFlowRepository;
use FlowEngine\Infrastructure\Analyzer\ProjectScanner;
use FlowEngine\Infrastructure\Analyzer\AstParser;
use FlowEngine\Infrastructure\Analyzer\FlowBuilder;
use FlowEngine\Domain\Flow\Node;
use FlowEngine\Domain\Contracts\ProjectContext;
use Tests\Support\TestProjectContext;
use Tests\Support\AlwaysPublicVisibilityPolicy;

final class AstFlowRepositoryTest extends TestCase
{
    private AstFlowRepository $repository;

    protected function setUp(): void
    {
        $scanner = $this->createMock(ProjectScanner::class);

        $fixtureFile = __DIR__ . '/../Fixtures/ExampleProject/App/src/Calculator.php';

        $scanner
            ->method('scan')
            ->with($this->isInstanceOf(ProjectContext::class))
            ->willReturn([$fixtureFile]);

        $nodeFactory = new DefaultNodeFactory();

        $this->repository = new AstFlowRepository(
            scanner: $scanner,
            parser: new AstParser($nodeFactory),
            builder: new FlowBuilder(
                new AlwaysPublicVisibilityPolicy()
            ),
            context: new TestProjectContext('/irrelevant')
        );
    }

    public function test_it_analyzes_project_and_returns_nodes(): void
    {
        $nodes = $this->repository->getNodes();

        $this->assertNotEmpty($nodes);
        $this->assertContainsOnlyInstancesOf(Node::class, $nodes);
    }

    public function test_it_finds_node_by_id(): void
    {
        $nodes = $this->repository->getNodes();

        // pega o ID REAL gerado pelo AST
        $firstNode = $nodes[0];
        $id = $firstNode->id();

        $node = $this->repository->getNode($id);

        $this->assertInstanceOf(Node::class, $node);
        $this->assertSame($id, $node->id());
    }

    public function test_it_throws_when_node_not_found(): void
    {
        $this->expectException(\LogicException::class);

        $this->repository->getNode('Definitely\\Unknown::method');
    }
}
