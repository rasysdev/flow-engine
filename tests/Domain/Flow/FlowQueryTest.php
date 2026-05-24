<?php

namespace Tests\Domain\Flow;

use FlowEngine\Application\Policy\DefaultNodeVisibilityPolicy;
use FlowEngine\Domain\Flow\DefaultNodeFactory;
use FlowEngine\Domain\Flow\Flow;
use FlowEngine\Domain\Flow\Node;
use FlowEngine\Domain\Flow\NodeCollection;
use FlowEngine\Infrastructure\Analyzer\FlowBuilder;
use PHPUnit\Framework\TestCase;

final class FlowQueryTest extends TestCase
{
    private Flow $flow;

    protected function setUp(): void
    {
        $factory = new DefaultNodeFactory();

        $nodes = [
            $factory->create('Calculator', 'sum', __FILE__, 10),
            $factory->create('Calculator', 'subtract', __FILE__, 20),
            $factory->create('Math', 'multiply', __FILE__, 30),
            $factory->create('Math', 'divide', __FILE__, 40),
        ];

        $builder = new FlowBuilder(new DefaultNodeVisibilityPolicy());
        $this->flow = $builder->build($nodes, []);
    }

    public function test_all_returns_nodes(): void
    {
        $nodes = $this->flow->query()->all();

        $this->assertCount(4, $nodes);
        $this->assertContainsOnlyInstancesOf(Node::class, $nodes);
    }

    public function test_first_returns_node(): void
    {
        $node = $this->flow->query()->first();

        $this->assertInstanceOf(Node::class, $node);
        $this->assertEquals('Calculator::sum', $node->id());
    }

    public function test_first_returns_null_on_empty_result(): void
    {
        $node = $this->flow->query()
            ->byClass('NonExistent')
            ->first();

        $this->assertNull($node);
    }

    public function test_by_class_filters_correctly(): void
    {
        $nodes = $this->flow->query()
            ->byClass('Calculator')
            ->all();

        $this->assertCount(2, $nodes);
        $this->assertEquals('Calculator::sum', $nodes[0]->id());
        $this->assertEquals('Calculator::subtract', $nodes[1]->id());
    }

    public function test_by_method_filters_correctly(): void
    {
        $nodes = $this->flow->query()
            ->byMethod('sum')
            ->all();

        $this->assertCount(1, $nodes);
        $this->assertEquals('Calculator::sum', $nodes[0]->id());
    }

    public function test_public_nodes_filters_correctly(): void
    {
        $nodes = $this->flow->query()
            ->publicNodes()
            ->all();

        $this->assertCount(4, $nodes);

        foreach ($nodes as $node) {
            $this->assertTrue($node->isPublic());
        }
    }

    public function test_to_collection_returns_node_collection(): void
    {
        $collection = $this->flow->query()
            ->byClass('Calculator')
            ->toCollection();

        $this->assertInstanceOf(NodeCollection::class, $collection);
        $this->assertCount(2, $collection->all());
    }

    public function test_count_returns_correct_number(): void
    {
        $count = $this->flow->query()
            ->byClass('Math')
            ->count();

        $this->assertEquals(2, $count);
    }

    public function test_exists_returns_true_when_results_found(): void
    {
        $exists = $this->flow->query()
            ->byClass('Calculator')
            ->exists();

        $this->assertTrue($exists);
    }

    public function test_exists_returns_false_when_no_results(): void
    {
        $exists = $this->flow->query()
            ->byClass('NonExistent')
            ->exists();

        $this->assertFalse($exists);
    }

    public function test_is_empty_returns_true_when_no_results(): void
    {
        $isEmpty = $this->flow->query()
            ->byClass('NonExistent')
            ->isEmpty();

        $this->assertTrue($isEmpty);
    }

    public function test_is_empty_returns_false_when_results_found(): void
    {
        $isEmpty = $this->flow->query()
            ->byClass('Calculator')
            ->isEmpty();

        $this->assertFalse($isEmpty);
    }

    public function test_chaining_multiple_filters(): void
    {
        $nodes = $this->flow->query()
            ->byClass('Calculator')
            ->publicNodes()
            ->all();

        $this->assertCount(2, $nodes);
    }

    public function test_query_is_immutable(): void
    {
        $query1 = $this->flow->query();
        $query2 = $query1->byClass('Calculator');

        $this->assertCount(4, $query1->all());
        $this->assertCount(2, $query2->all());
    }

    public function test_find_node_returns_node(): void
    {
        $node = $this->flow->findNode('Calculator::sum');

        $this->assertInstanceOf(Node::class, $node);
        $this->assertEquals('Calculator::sum', $node->id());
    }

    public function test_find_node_returns_null_when_not_found(): void
    {
        $node = $this->flow->findNode('NonExistent::method');

        $this->assertNull($node);
    }
}