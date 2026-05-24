<?php

declare(strict_types=1);

namespace Tests\Application\ProjectMap;

use FlowEngine\Application\DTO\SymbolDTO;
use FlowEngine\Application\Policy\DefaultNodeVisibilityPolicy;
use FlowEngine\Application\ProjectMap\ProjectFindBuilder;
use FlowEngine\Domain\Flow\Flow;
use FlowEngine\Domain\Flow\Node;
use FlowEngine\Domain\Flow\SymbolIndex;
use FlowEngine\Infrastructure\Analyzer\FlowBuilder;
use PHPUnit\Framework\TestCase;

final class ProjectFindBuilderTest extends TestCase
{
    private ProjectFindBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new ProjectFindBuilder();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function buildFlow(array $nodes, ?SymbolIndex $symbols = null): Flow
    {
        return (new FlowBuilder(new DefaultNodeVisibilityPolicy()))->build($nodes, [], $symbols);
    }

    /** @return Node[] */
    private function makeNodes(): array
    {
        $f = __FILE__;
        return [
            new Node('App\\Http\\Controllers\\UserController',  'index',    $f, 10),
            new Node('App\\Http\\Controllers\\UserController',  'show',     $f, 20),
            new Node('App\\Http\\Controllers\\UserController',  'store',    $f, 30),
            new Node('App\\Http\\Controllers\\OrderController', 'index',    $f, 10),
            new Node('App\\Application\\UserService',           'save',     $f, 5),
            new Node('App\\Application\\OrderService',          'save',     $f, 5),
            new Node('App\\Domain\\User',                       'getEmail', $f, 2),
        ];
    }

    // -------------------------------------------------------------------------
    // Empty query
    // -------------------------------------------------------------------------

    public function test_empty_query_returns_empty_matches(): void
    {
        $flow = $this->buildFlow($this->makeNodes());

        $result = $this->builder->findInProject('/project', $flow, '', null, 10);

        $this->assertSame('node_find', $result['kind']);
        $this->assertSame([], $result['matches']);
        $this->assertFalse($result['truncated']);
    }

    // -------------------------------------------------------------------------
    // Exact match case-insensitive
    // -------------------------------------------------------------------------

    public function test_exact_match_class_name_case_insensitive(): void
    {
        $flow = $this->buildFlow($this->makeNodes());

        $result = $this->builder->findInProject('/project', $flow, 'UserController', null, 10);

        $this->assertCount(1, $result['matches']);
        $this->assertSame('App\\Http\\Controllers\\UserController', $result['matches'][0]['id']);
    }

    public function test_case_insensitive_match(): void
    {
        $flow = $this->buildFlow($this->makeNodes());

        $upper = $this->builder->findInProject('/project', $flow, 'USERCONTROLLER', null, 10);
        $lower = $this->builder->findInProject('/project', $flow, 'usercontroller', null, 10);

        $this->assertSame($upper['matches'], $lower['matches']);
    }

    // -------------------------------------------------------------------------
    // Substring partial match
    // -------------------------------------------------------------------------

    public function test_substring_partial_match(): void
    {
        $flow = $this->buildFlow($this->makeNodes());

        $result = $this->builder->findInProject('/project', $flow, 'Controller', null, 10);

        $this->assertCount(2, $result['matches']);
        $ids = array_column($result['matches'], 'id');
        $this->assertContains('App\\Http\\Controllers\\UserController', $ids);
        $this->assertContains('App\\Http\\Controllers\\OrderController', $ids);
    }

    // -------------------------------------------------------------------------
    // Methods consolidated per class
    // -------------------------------------------------------------------------

    public function test_methods_consolidated_per_class(): void
    {
        $flow = $this->buildFlow($this->makeNodes());

        $result = $this->builder->findInProject('/project', $flow, 'UserController', null, 10);

        $match = $result['matches'][0];
        sort($match['methods']);
        $this->assertSame(['index', 'show', 'store'], $match['methods']);
    }

    // -------------------------------------------------------------------------
    // Type filter
    // -------------------------------------------------------------------------

    public function test_filter_by_type_class(): void
    {
        $flow = $this->buildFlow($this->makeNodes());

        // 'user' matches UserController (class) and User (class) — but not methods
        $result = $this->builder->findInProject('/project', $flow, 'user', 'class', 10);

        $ids = array_column($result['matches'], 'id');
        foreach ($ids as $id) {
            $this->assertStringContainsStringIgnoringCase('user', strtolower($id));
        }
    }

    public function test_filter_by_type_method(): void
    {
        $flow = $this->buildFlow($this->makeNodes());

        $result = $this->builder->findInProject('/project', $flow, 'save', 'method', 10);

        $this->assertCount(2, $result['matches']);
        $ids = array_column($result['matches'], 'id');
        $this->assertContains('App\\Application\\UserService', $ids);
        $this->assertContains('App\\Application\\OrderService', $ids);
    }

    public function test_filter_by_type_namespace(): void
    {
        $flow = $this->buildFlow($this->makeNodes());

        $result = $this->builder->findInProject('/project', $flow, 'Application', 'namespace', 10);

        $this->assertCount(2, $result['matches']);
        $ids = array_column($result['matches'], 'id');
        $this->assertContains('App\\Application\\UserService', $ids);
        $this->assertContains('App\\Application\\OrderService', $ids);
    }

    // -------------------------------------------------------------------------
    // Limit and truncated flag
    // -------------------------------------------------------------------------

    public function test_respects_limit_and_sets_truncated_true(): void
    {
        $flow = $this->buildFlow($this->makeNodes());

        // All nodes match 'app' in namespace — 5 unique classes
        $result = $this->builder->findInProject('/project', $flow, 'app', null, 2);

        $this->assertCount(2, $result['matches']);
        $this->assertTrue($result['truncated']);
    }

    public function test_truncated_false_when_within_limit(): void
    {
        $flow = $this->buildFlow($this->makeNodes());

        $result = $this->builder->findInProject('/project', $flow, 'UserController', null, 10);

        $this->assertFalse($result['truncated']);
    }

    public function test_find_many_returns_batch_results_and_deduplicates_queries(): void
    {
        $flow = $this->buildFlow($this->makeNodes());

        $result = $this->builder->findManyInProject(
            '/project',
            $flow,
            ['UserController', 'save', 'USERCONTROLLER'],
            null,
            10
        );

        $this->assertSame('node_find_batch', $result['kind']);
        $this->assertSame(['usercontroller', 'save'], $result['queries']);
        $this->assertSame(2, $result['totalRequested']);
        $this->assertSame(2, $result['returned']);
        $this->assertFalse($result['truncated']);

        $this->assertSame('node_find', $result['results'][0]['kind']);
        $this->assertSame('usercontroller', $result['results'][0]['query']);
        $this->assertSame('save', $result['results'][1]['query']);
        $this->assertCount(1, $result['results'][0]['matches']);
        $this->assertCount(2, $result['results'][1]['matches']);
    }

    public function test_find_many_sets_top_level_truncated_when_any_query_truncates(): void
    {
        $flow = $this->buildFlow($this->makeNodes());

        $result = $this->builder->findManyInProject('/project', $flow, ['app', 'UserController'], null, 2);

        $this->assertTrue($result['truncated']);
        $this->assertTrue($result['results'][0]['truncated']);
        $this->assertFalse($result['results'][1]['truncated']);
    }

    // -------------------------------------------------------------------------
    // fan_in present in output
    // -------------------------------------------------------------------------

    public function test_fan_in_present_in_each_match(): void
    {
        $flow = $this->buildFlow($this->makeNodes());

        $result = $this->builder->findInProject('/project', $flow, 'UserController', null, 10);

        $this->assertArrayHasKey('fan_in', $result['matches'][0]);
        $this->assertIsInt($result['matches'][0]['fan_in']);
    }

    // -------------------------------------------------------------------------
    // Output shape
    // -------------------------------------------------------------------------

    public function test_output_shape(): void
    {
        $flow = $this->buildFlow($this->makeNodes());

        $result = $this->builder->findInProject('/project', $flow, 'UserController', null, 10);

        $this->assertSame('node_find', $result['kind']);
        $this->assertSame('usercontroller', $result['query']);
        $this->assertArrayHasKey('matches', $result);
        $this->assertArrayHasKey('truncated', $result);

        $match = $result['matches'][0];
        $this->assertArrayHasKey('type', $match);
        $this->assertArrayHasKey('id', $match);
        $this->assertArrayHasKey('file', $match);
        $this->assertArrayHasKey('methods', $match);
        $this->assertArrayHasKey('fan_in', $match);
    }

    // -------------------------------------------------------------------------
    // No-match
    // -------------------------------------------------------------------------

    public function test_no_match_returns_empty_array(): void
    {
        $flow = $this->buildFlow($this->makeNodes());

        $result = $this->builder->findInProject('/project', $flow, 'XyzNonExistentAbc123', null, 10);

        $this->assertSame([], $result['matches']);
        $this->assertFalse($result['truncated']);
    }

    // -------------------------------------------------------------------------
    // Symbol type
    // -------------------------------------------------------------------------

    public function test_type_symbol_returns_symbol_results_not_nodes(): void
    {
        $symbols = new SymbolIndex([
            SymbolDTO::make('TriangleAlertIcon', 'import', '/app/Component.tsx', 1, 'lucide-react'),
            SymbolDTO::make('AlertCircle', 'import', '/app/Component.tsx', 2, 'lucide-react'),
            SymbolDTO::make('useState', 'import', '/app/Component.tsx', 3, 'react'),
        ]);
        $flow = $this->buildFlow($this->makeNodes(), $symbols);

        $result = $this->builder->findInProject('/project', $flow, 'alert', 'symbol', 10);

        $this->assertSame([], $result['matches']);
        $this->assertArrayHasKey('symbols', $result);
        $this->assertCount(2, $result['symbols']);
        $symbolNames = array_column($result['symbols'], 'name');
        $this->assertContains('TriangleAlertIcon', $symbolNames);
        $this->assertContains('AlertCircle', $symbolNames);
    }

    public function test_type_null_falls_back_to_symbols_when_no_node_matches(): void
    {
        $symbols = new SymbolIndex([
            SymbolDTO::make('lastError', 'const', '/app/state.ts', 5),
        ]);
        $flow = $this->buildFlow([], $symbols);

        $result = $this->builder->findInProject('/project', $flow, 'lasterror', null, 10);

        $this->assertSame([], $result['matches']);
        $this->assertCount(1, $result['symbols']);
        $this->assertSame('lastError', $result['symbols'][0]['name']);
    }
}
