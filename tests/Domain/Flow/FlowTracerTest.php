<?php

namespace Tests\Domain\Flow;

use FlowEngine\Domain\Flow\Edge;
use FlowEngine\Domain\Flow\Flow;
use FlowEngine\Domain\Flow\FlowTrace;
use FlowEngine\Domain\Flow\FlowTracer;
use FlowEngine\Domain\Flow\Node;
use FlowEngine\Domain\Flow\TraceDirection;
use PHPUnit\Framework\TestCase;

final class FlowTracerTest extends TestCase
{
    /**
     * Graph: Controller::store → Service::save → Repository::persist
     */
    private function buildLinearFlow(): Flow
    {
        $nodes = [
            new Node('Controller', 'store', __FILE__, 1),
            new Node('Service', 'save', __FILE__, 2),
            new Node('Repository', 'persist', __FILE__, 3),
        ];

        $edges = [
            new Edge('Controller::store', 'Service::save', 'save', 'method_call'),
            new Edge('Service::save', 'Repository::persist', 'persist', 'method_call'),
        ];

        return new Flow($nodes, $edges);
    }

    /**
     * Graph with mixed edge types:
     * Controller::index → Service::list (method_call)
     * Service::list → Repository::findAll (method_call)
     * Service::list → Cache::$items (property_access)
     */
    private function buildMixedFlow(): Flow
    {
        $nodes = [
            new Node('Controller', 'index', __FILE__, 1),
            new Node('Service', 'list', __FILE__, 2),
            new Node('Repository', 'findAll', __FILE__, 3),
            new Node('Cache', 'items', __FILE__, 4),
        ];

        $edges = [
            new Edge('Controller::index', 'Service::list', 'list', 'method_call'),
            new Edge('Service::list', 'Repository::findAll', 'findAll', 'method_call'),
            new Edge('Service::list', 'Cache::items', 'items', 'property_access'),
        ];

        return new Flow($nodes, $edges);
    }

    /**
     * Graph with cycle: A → B → C → A
     */
    private function buildCyclicFlow(): Flow
    {
        $nodes = [
            new Node('A', 'run', __FILE__, 1),
            new Node('B', 'run', __FILE__, 2),
            new Node('C', 'run', __FILE__, 3),
        ];

        $edges = [
            new Edge('A::run', 'B::run', 'run', 'method_call'),
            new Edge('B::run', 'C::run', 'run', 'method_call'),
            new Edge('C::run', 'A::run', 'run', 'method_call'),
        ];

        return new Flow($nodes, $edges);
    }

    /** @test */
    public function test_trace_downstream_returns_called_nodes(): void
    {
        $flow = $this->buildLinearFlow();
        $tracer = new FlowTracer($flow);

        $trace = $tracer->trace('Controller::store', TraceDirection::DOWNSTREAM);

        $nodeIds = array_map(fn(Node $n) => $n->id(), $trace->nodes());

        $this->assertContains('Controller::store', $nodeIds);
        $this->assertContains('Service::save', $nodeIds);
        $this->assertContains('Repository::persist', $nodeIds);
        $this->assertEquals(3, $trace->nodeCount());
        $this->assertEquals(TraceDirection::DOWNSTREAM, $trace->direction());
    }

    /** @test */
    public function test_trace_upstream_returns_callers(): void
    {
        $flow = $this->buildLinearFlow();
        $tracer = new FlowTracer($flow);

        $trace = $tracer->trace('Repository::persist', TraceDirection::UPSTREAM);

        $nodeIds = array_map(fn(Node $n) => $n->id(), $trace->nodes());

        $this->assertContains('Repository::persist', $nodeIds);
        $this->assertContains('Service::save', $nodeIds);
        $this->assertContains('Controller::store', $nodeIds);
        $this->assertEquals(3, $trace->nodeCount());
    }

    /** @test */
    public function test_trace_both_directions(): void
    {
        $flow = $this->buildLinearFlow();
        $tracer = new FlowTracer($flow);

        // Trace from middle node (Service::save) in both directions
        $trace = $tracer->trace('Service::save', TraceDirection::BOTH);

        $nodeIds = array_map(fn(Node $n) => $n->id(), $trace->nodes());

        $this->assertContains('Controller::store', $nodeIds);
        $this->assertContains('Service::save', $nodeIds);
        $this->assertContains('Repository::persist', $nodeIds);
        $this->assertEquals(3, $trace->nodeCount());
    }

    /** @test */
    public function test_trace_respects_max_depth(): void
    {
        $flow = $this->buildLinearFlow();
        $tracer = new FlowTracer($flow);

        // maxDepth=1 should only get Controller → Service (not Repository)
        $trace = $tracer->trace('Controller::store', TraceDirection::DOWNSTREAM, maxDepth: 1);

        $nodeIds = array_map(fn(Node $n) => $n->id(), $trace->nodes());

        $this->assertContains('Controller::store', $nodeIds);
        $this->assertContains('Service::save', $nodeIds);
        $this->assertNotContains('Repository::persist', $nodeIds);
        $this->assertEquals(2, $trace->nodeCount());
    }

    /** @test */
    public function test_trace_handles_cycles(): void
    {
        $flow = $this->buildCyclicFlow();
        $tracer = new FlowTracer($flow);

        // Should not loop infinitely — visits each node once
        $trace = $tracer->trace('A::run', TraceDirection::DOWNSTREAM, maxDepth: 10);

        $nodeIds = array_map(fn(Node $n) => $n->id(), $trace->nodes());

        $this->assertContains('A::run', $nodeIds);
        $this->assertContains('B::run', $nodeIds);
        $this->assertContains('C::run', $nodeIds);
        $this->assertEquals(3, $trace->nodeCount());
    }

    /** @test */
    public function test_trace_filters_by_edge_type(): void
    {
        $flow = $this->buildMixedFlow();
        $tracer = new FlowTracer($flow);

        // Only follow method_call edges
        $trace = $tracer->trace(
            'Controller::index',
            TraceDirection::DOWNSTREAM,
            maxDepth: 10,
            edgeTypeFilter: ['method_call']
        );

        $nodeIds = array_map(fn(Node $n) => $n->id(), $trace->nodes());

        $this->assertContains('Repository::findAll', $nodeIds);
        $this->assertNotContains('Cache::items', $nodeIds);
    }

    /** @test */
    public function test_trace_from_leaf_node_returns_only_start(): void
    {
        $flow = $this->buildLinearFlow();
        $tracer = new FlowTracer($flow);

        // Repository::persist has no downstream edges
        $trace = $tracer->trace('Repository::persist', TraceDirection::DOWNSTREAM);

        $this->assertEquals(1, $trace->nodeCount());
        $this->assertEquals(0, $trace->edgeCount());
        $this->assertEquals('Repository::persist', $trace->startNodeId());
    }

    /** @test */
    public function test_trace_returns_edges_in_subgraph(): void
    {
        $flow = $this->buildLinearFlow();
        $tracer = new FlowTracer($flow);

        $trace = $tracer->trace('Controller::store', TraceDirection::DOWNSTREAM);

        $this->assertEquals(2, $trace->edgeCount());

        $edgeStrings = array_map(
            fn(Edge $e) => $e->from() . '->' . $e->to(),
            $trace->edges()
        );

        $this->assertContains('Controller::store->Service::save', $edgeStrings);
        $this->assertContains('Service::save->Repository::persist', $edgeStrings);
    }

    /** @test */
    public function test_trace_throws_for_unknown_node(): void
    {
        $flow = $this->buildLinearFlow();
        $tracer = new FlowTracer($flow);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Node not found: Unknown::method');

        $tracer->trace('Unknown::method');
    }

    /** @test */
    public function test_trace_throws_for_invalid_max_depth(): void
    {
        $flow = $this->buildLinearFlow();
        $tracer = new FlowTracer($flow);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('maxDepth must be at least 1');

        $tracer->trace('Controller::store', maxDepth: 0);
    }

    /** @test */
    public function test_trace_depth_is_correct(): void
    {
        $flow = $this->buildLinearFlow();
        $tracer = new FlowTracer($flow);

        $trace = $tracer->trace('Controller::store', TraceDirection::DOWNSTREAM);

        $this->assertEquals(2, $trace->depth());
    }

    /** @test */
    public function test_trace_direction_enum(): void
    {
        $this->assertEquals('downstream', TraceDirection::DOWNSTREAM->value);
        $this->assertEquals('upstream', TraceDirection::UPSTREAM->value);
        $this->assertEquals('both', TraceDirection::BOTH->value);

        $this->assertEquals(TraceDirection::DOWNSTREAM, TraceDirection::fromString('downstream'));
        $this->assertEquals(TraceDirection::UPSTREAM, TraceDirection::fromString('UPSTREAM'));
    }

    // --- extractSubgraph ---

    public function test_extract_subgraph_returns_all_nodes_bidirectional(): void
    {
        $flow = $this->buildLinearFlow();
        $tracer = new FlowTracer($flow);

        // From middle node: should include upstream (Controller) + downstream (Repository)
        $ids = $tracer->extractSubgraph('Service::save');

        $this->assertArrayHasKey('Controller::store', $ids);
        $this->assertArrayHasKey('Service::save', $ids);
        $this->assertArrayHasKey('Repository::persist', $ids);
        $this->assertCount(3, $ids);
    }

    public function test_extract_subgraph_downstream_only(): void
    {
        $flow = $this->buildLinearFlow();
        $tracer = new FlowTracer($flow);

        $ids = $tracer->extractSubgraph('Service::save', 5, TraceDirection::DOWNSTREAM);

        $this->assertArrayHasKey('Service::save', $ids);
        $this->assertArrayHasKey('Repository::persist', $ids);
        $this->assertArrayNotHasKey('Controller::store', $ids);
        $this->assertCount(2, $ids);
    }

    public function test_extract_subgraph_throws_for_unknown_node(): void
    {
        $flow = $this->buildLinearFlow();
        $tracer = new FlowTracer($flow);

        $this->expectException(\InvalidArgumentException::class);
        $tracer->extractSubgraph('Unknown::method');
    }

    public function test_extract_subgraph_values_are_true(): void
    {
        $flow = $this->buildLinearFlow();
        $tracer = new FlowTracer($flow);

        $ids = $tracer->extractSubgraph('Controller::store', 5, TraceDirection::DOWNSTREAM);

        foreach ($ids as $value) {
            $this->assertTrue($value);
        }
    }
}
