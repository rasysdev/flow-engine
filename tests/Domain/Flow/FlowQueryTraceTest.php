<?php

namespace Tests\Domain\Flow;

use FlowEngine\Domain\Flow\Edge;
use FlowEngine\Domain\Flow\Flow;
use FlowEngine\Domain\Flow\FlowTrace;
use FlowEngine\Domain\Flow\Node;
use PHPUnit\Framework\TestCase;

final class FlowQueryTraceTest extends TestCase
{
    private function buildFlow(): Flow
    {
        $nodes = [
            new Node('Controller', 'store', __FILE__, 1),
            new Node('Service', 'save', __FILE__, 2),
            new Node('Repository', 'persist', __FILE__, 3),
            new Node('Cache', 'put', __FILE__, 4),
        ];

        $edges = [
            new Edge('Controller::store', 'Service::save', 'save', 'method_call'),
            new Edge('Service::save', 'Repository::persist', 'persist', 'method_call'),
            new Edge('Service::save', 'Cache::put', 'put', 'property_access'),
        ];

        return new Flow($nodes, $edges);
    }

    public function test_fluent_trace_downstream(): void
    {
        $flow = $this->buildFlow();

        $result = $flow->query()
            ->from('Controller::store')
            ->downstream()
            ->trace();

        $this->assertInstanceOf(FlowTrace::class, $result);
        $this->assertEquals('downstream', $result->direction()->value);
        $this->assertGreaterThan(1, count($result->nodes()));
    }

    public function test_fluent_trace_upstream_with_depth(): void
    {
        $flow = $this->buildFlow();

        $result = $flow->query()
            ->from('Repository::persist')
            ->upstream()
            ->maxDepth(1)
            ->trace();

        $this->assertEquals('upstream', $result->direction()->value);
        $this->assertEquals(1, $result->maxDepth());

        $nodeIds = array_map(static fn(Node $n): string => $n->id(), $result->nodes());
        $this->assertContains('Service::save', $nodeIds);
    }

    public function test_fluent_trace_with_edge_filter(): void
    {
        $flow = $this->buildFlow();

        $result = $flow->query()
            ->from('Controller::store')
            ->downstream()
            ->onlyMethodCalls()
            ->trace();

        $nodeIds = array_map(static fn(Node $n): string => $n->id(), $result->nodes());

        $this->assertContains('Repository::persist', $nodeIds);
        $this->assertNotContains('Cache::put', $nodeIds);
    }

    public function test_fluent_trace_nodes_shortcut(): void
    {
        $flow = $this->buildFlow();

        $nodes = $flow->query()
            ->from('Controller::store')
            ->downstream()
            ->nodes();

        $this->assertIsArray($nodes);
        $this->assertGreaterThan(1, count($nodes));
    }

    public function test_fluent_trace_with_custom_edge_types(): void
    {
        $flow = $this->buildFlow();

        $flow->query()
            ->from('Controller::store')
            ->downstream()
            ->edgeTypes(['property_access'])
            ->trace();

        $result2 = $flow->query()
            ->from('Service::save')
            ->downstream()
            ->edgeTypes(['property_access'])
            ->trace();

        $nodeIds = array_map(static fn(Node $n): string => $n->id(), $result2->nodes());
        $this->assertContains('Cache::put', $nodeIds);
        $this->assertNotContains('Repository::persist', $nodeIds);
    }

    public function test_fluent_trace_both_directions(): void
    {
        $flow = $this->buildFlow();

        $result = $flow->query()
            ->from('Service::save')
            ->both()
            ->trace();

        $nodeIds = array_map(static fn(Node $n): string => $n->id(), $result->nodes());

        $this->assertContains('Controller::store', $nodeIds);
        $this->assertContains('Repository::persist', $nodeIds);
    }
}