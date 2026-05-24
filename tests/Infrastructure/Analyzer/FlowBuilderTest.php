<?php

namespace Tests\Infrastructure\Analyzer;

use FlowEngine\Application\Policy\DefaultNodeVisibilityPolicy;
use FlowEngine\Domain\Flow\Node;
use FlowEngine\Infrastructure\Analyzer\FlowBuilder;
use PHPUnit\Framework\TestCase;

final class FlowBuilderTest extends TestCase
{
    public function test_build_applies_visibility_policy(): void
    {
        $builder = new FlowBuilder(new DefaultNodeVisibilityPolicy());

        $node = new Node('Calculator', 'sum', __FILE__, 10);

        $this->assertFalse($node->hasEvaluatedVisibility());

        $flow = $builder->build([$node], []);

        $nodeFromFlow = $flow->nodes()[0];

        $this->assertTrue($nodeFromFlow->hasEvaluatedVisibility());
    }

    public function test_build_dedupes_duplicate_ids_silently(): void
    {
        $builder = new FlowBuilder(new DefaultNodeVisibilityPolicy());

        $node1 = new Node('Calculator', 'sum', __FILE__, 10);
        $node2 = new Node('Calculator', 'sum', __FILE__, 20);

        $flow = $builder->build([$node1, $node2], []);

        $this->assertCount(1, $flow->nodes());
        $this->assertSame(['Calculator::sum'], $builder->lastDuplicateIds());
    }

    public function test_build_validates_all_nodes(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $builder = new FlowBuilder(new DefaultNodeVisibilityPolicy());

        $invalidNode = new Node('', 'sum', __FILE__, 10);

        $builder->build([$invalidNode], []);
    }
    public function test_build_accepts_nodes_with_different_ids(): void
    {
        $builder = new FlowBuilder(new DefaultNodeVisibilityPolicy());

        $node1 = new Node('Calculator', 'sum', __FILE__, 10);
        $node2 = new Node('Calculator', 'subtract', __FILE__, 20);
        $node3 = new Node('Math', 'sum', __FILE__, 30);

        $flow = $builder->build([$node1, $node2, $node3], []);

        $this->assertCount(3, $flow->nodes());
    }

    public function test_build_reports_each_duplicate_id_once_sorted(): void
    {
        $builder = new FlowBuilder(new DefaultNodeVisibilityPolicy());

        $node1 = new Node('Calculator', 'sum', __FILE__, 10);
        $node2 = new Node('Calculator', 'sum', __FILE__, 50);
        $node3 = new Node('Calculator', 'sum', __FILE__, 100);
        $node4 = new Node('Math', 'add', __FILE__, 200);
        $node5 = new Node('Math', 'add', __FILE__, 210);

        $flow = $builder->build([$node1, $node2, $node3, $node4, $node5], []);

        $this->assertCount(2, $flow->nodes());
        $this->assertSame(['Calculator::sum', 'Math::add'], $builder->lastDuplicateIds());
    }

    public function test_build_keeps_last_occurrence_on_duplicate_ids(): void
    {
        $builder = new FlowBuilder(new DefaultNodeVisibilityPolicy());

        $signature = new Node('Calculator', 'sum', __FILE__, 10);
        $body      = new Node('Calculator', 'sum', __FILE__, 42);

        $flow = $builder->build([$signature, $body], []);

        $nodes = $flow->nodes();
        $this->assertCount(1, $nodes);
        $this->assertSame(42, $nodes[0]->line());
    }

    public function test_last_duplicate_ids_empty_when_no_duplicates(): void
    {
        $builder = new FlowBuilder(new DefaultNodeVisibilityPolicy());

        $node = new Node('Calculator', 'sum', __FILE__, 10);
        $builder->build([$node], []);

        $this->assertSame([], $builder->lastDuplicateIds());
    }

    public function test_build_with_empty_nodes_array(): void
    {
        $builder = new FlowBuilder(new DefaultNodeVisibilityPolicy());

        $flow = $builder->build([], []);

        $this->assertCount(0, $flow->nodes());
        $this->assertCount(0, $flow->edges());
    }

    public function test_build_with_single_node(): void
    {
        $builder = new FlowBuilder(new DefaultNodeVisibilityPolicy());

        $node = new Node('Calculator', 'sum', __FILE__, 10);

        $flow = $builder->build([$node], []);

        $this->assertCount(1, $flow->nodes());
    }
}