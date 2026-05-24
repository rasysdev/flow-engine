<?php

namespace Tests\Domain\Flow\Query\Filters;

use FlowEngine\Domain\Flow\Edge;
use FlowEngine\Domain\Flow\Flow;
use FlowEngine\Domain\Flow\Node;
use FlowEngine\Domain\Flow\NodeCollection;
use FlowEngine\Domain\Flow\Query\Filters\EntrypointFilter;
use PHPUnit\Framework\TestCase;

final class EntrypointFilterTest extends TestCase
{
    public function test_finds_entrypoints(): void
    {
        $nodeA = new Node('A', 'x', __FILE__, 1);
        $nodeB = new Node('B', 'y', __FILE__, 2);
        $nodeC = new Node('C', 'z', __FILE__, 3);

        // A chama B, B chama C
        // Então A é entry point (ninguém chama A)
        $edges = [
            new Edge('A::x', 'B::y', 'y'),
            new Edge('B::y', 'C::z', 'z'),
        ];

        $flow = new Flow([$nodeA, $nodeB, $nodeC], $edges);
        $nodes = new NodeCollection([$nodeA, $nodeB, $nodeC]);

        $filter = new EntrypointFilter($flow);
        $result = $filter->apply($nodes);

        $this->assertCount(1, $result->all());
        $this->assertEquals('A::x', $result->all()[0]->id());
    }

    public function test_returns_all_when_no_edges(): void
    {
        $nodeA = new Node('A', 'x', __FILE__, 1);
        $nodeB = new Node('B', 'y', __FILE__, 2);

        $flow = new Flow([$nodeA, $nodeB], []); // sem edges
        $nodes = new NodeCollection([$nodeA, $nodeB]);

        $filter = new EntrypointFilter($flow);
        $result = $filter->apply($nodes);

        // Sem edges, todos são entry points
        $this->assertCount(2, $result->all());
    }

    public function test_handles_multiple_entrypoints(): void
    {
        $nodeA = new Node('A', 'x', __FILE__, 1);
        $nodeB = new Node('B', 'y', __FILE__, 2);
        $nodeC = new Node('C', 'z', __FILE__, 3);

        // A e B chamam C, mas ninguém chama A ou B
        $edges = [
            new Edge('A::x', 'C::z', 'z'),
            new Edge('B::y', 'C::z', 'z'),
        ];

        $flow = new Flow([$nodeA, $nodeB, $nodeC], $edges);
        $nodes = new NodeCollection([$nodeA, $nodeB, $nodeC]);

        $filter = new EntrypointFilter($flow);
        $result = $filter->apply($nodes);

        $this->assertCount(2, $result->all());
    }
}
