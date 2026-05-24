<?php

namespace Tests\Domain\Flow;

use FlowEngine\Application\Policy\DefaultNodeVisibilityPolicy;
use FlowEngine\Domain\Flow\DefaultNodeFactory;
use FlowEngine\Domain\Flow\Edge;
use FlowEngine\Domain\Flow\Flow;
use FlowEngine\Infrastructure\Analyzer\FlowBuilder;
use PHPUnit\Framework\TestCase;

final class FlowQueryAdvancedTest extends TestCase
{
    public function test_by_namespace_filters_correctly(): void
    {
        $factory = new DefaultNodeFactory();
        
        $nodes = [
            $factory->create('App\\Services\\UserService', 'create', __FILE__, 1),
            $factory->create('App\\Services\\AuthService', 'login', __FILE__, 2),
            $factory->create('App\\Controllers\\UserController', 'index', __FILE__, 3),
        ];

        $builder = new FlowBuilder(new DefaultNodeVisibilityPolicy());
        $flow = $builder->build($nodes, []);

        $result = $flow->query()
            ->byNamespace('App\\Services')
            ->all();

        $this->assertCount(2, $result);
    }

    public function test_entrypoints_finds_nodes_without_callers(): void
    {
        $factory = new DefaultNodeFactory();
        
        $nodeA = $factory->create('A', 'entry', __FILE__, 1);
        $nodeB = $factory->create('B', 'called', __FILE__, 2);

        $edges = [new Edge('A::entry', 'B::called', 'called')];

        $builder = new FlowBuilder(new DefaultNodeVisibilityPolicy());
        $flow = $builder->build([$nodeA, $nodeB], $edges);

        $result = $flow->query()
            ->entrypoints()
            ->all();

        $this->assertCount(1, $result);
        $this->assertEquals('A::entry', $result[0]->id());
    }

    public function test_leaf_nodes_finds_nodes_that_call_nobody(): void
    {
        $factory = new DefaultNodeFactory();
        
        $nodeA = $factory->create('A', 'caller', __FILE__, 1);
        $nodeB = $factory->create('B', 'leaf', __FILE__, 2);

        $edges = [new Edge('A::caller', 'B::leaf', 'leaf')];

        $builder = new FlowBuilder(new DefaultNodeVisibilityPolicy());
        $flow = $builder->build([$nodeA, $nodeB], $edges);

        $result = $flow->query()
            ->leafNodes()
            ->all();

        $this->assertCount(1, $result);
        $this->assertEquals('B::leaf', $result[0]->id());
    }

    public function test_exclude_vendor_is_alias_for_only_application_code(): void
    {
        $factory = new DefaultNodeFactory();
        
        $nodes = [
            $factory->create('App\\UserService', 'create', __FILE__, 1),
            $factory->create('Vendor\\Package\\Helper', 'help', __FILE__, 2),
        ];

        $builder = new FlowBuilder(new DefaultNodeVisibilityPolicy());
        $flow = $builder->build($nodes, []);

        $result1 = $flow->query()->excludeVendor()->all();
        $result2 = $flow->query()->onlyApplicationCode()->all();

        // Ambos devem retornar o mesmo resultado
        $this->assertEquals(count($result1), count($result2));
        
        if (count($result1) > 0 && count($result2) > 0) {
            $this->assertEquals($result1[0]->id(), $result2[0]->id());
        }
    }

    public function test_chaining_advanced_queries(): void
    {
        $factory = new DefaultNodeFactory();
        
        $nodeA = $factory->create('App\\Services\\Entry', 'start', __FILE__, 1);
        $nodeB = $factory->create('App\\Services\\Worker', 'work', __FILE__, 2);
        $nodeC = $factory->create('App\\Helpers\\Leaf', 'format', __FILE__, 3);

        $edges = [
            new Edge('App\\Services\\Entry::start', 'App\\Services\\Worker::work', 'work'),
            new Edge('App\\Services\\Worker::work', 'App\\Helpers\\Leaf::format', 'format'),
        ];

        $builder = new FlowBuilder(new DefaultNodeVisibilityPolicy());
        $flow = $builder->build([$nodeA, $nodeB, $nodeC], $edges);

        $result = $flow->query()
            ->byNamespace('App\\Services')
            ->entrypoints()
            ->all();

        $this->assertCount(1, $result);
        $this->assertEquals('App\\Services\\Entry::start', $result[0]->id());
    }
}
