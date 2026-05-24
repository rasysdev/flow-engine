<?php

declare(strict_types=1);

namespace Tests\Application\ProjectMap;

use FlowEngine\Application\AppMap\ServiceInfo;
use FlowEngine\Application\DTO\ProjectBatchLookupDTO;
use FlowEngine\Application\Policy\DefaultNodeVisibilityPolicy;
use FlowEngine\Application\ProjectMap\ProjectLookupBuilder;
use FlowEngine\Domain\Flow\Edge;
use FlowEngine\Domain\Flow\Flow;
use FlowEngine\Domain\Flow\Node;
use FlowEngine\Infrastructure\Analyzer\FlowBuilder;
use PHPUnit\Framework\TestCase;

final class ProjectLookupBuilderBatchTest extends TestCase
{
    private ProjectLookupBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new ProjectLookupBuilder();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function buildFlow(array $nodes, array $edges = []): Flow
    {
        return (new FlowBuilder(new DefaultNodeVisibilityPolicy()))->build($nodes, $edges);
    }

    private function f(): string
    {
        return __FILE__;
    }

    /**
     * Returns nodes that look like real entrypoints (Http controllers).
     * The fixture maps to what ProjectMapBuilder::actionableEntrypoints expects.
     */
    private function makeEntrypointNodes(): array
    {
        $f = $this->f();
        return [
            new Node('App\\Http\\Controllers\\OrderController',  'store',   $f, 10),
            new Node('App\\Http\\Controllers\\OrderController',  'index',   $f, 20),
            new Node('App\\Http\\Controllers\\UserController',   'index',   $f, 30),
            new Node('App\\Application\\OrderService',           'save',    $f, 5),
        ];
    }

    // -------------------------------------------------------------------------
    // Scenario 1: 3 valid FQNs → 3 results, notFound=[], truncated=false
    // -------------------------------------------------------------------------

    public function test_batch_three_valid_nodes_returns_three_results(): void
    {
        $nodes = $this->makeEntrypointNodes();
        $flow = $this->buildFlow($nodes);

        $targets = [
            'App\\Http\\Controllers\\OrderController::store',
            'App\\Http\\Controllers\\OrderController::index',
            'App\\Http\\Controllers\\UserController::index',
        ];

        $result = $this->builder->lookupProjectBatch('/project', $flow, 'node', $targets, 'summary', 1, 20);

        $this->assertInstanceOf(ProjectBatchLookupDTO::class, $result);
        $this->assertSame('lookup_batch_result', $result->kind);
        $this->assertCount(3, $result->results);
        $this->assertSame([], $result->notFound);
        $this->assertFalse($result->truncated);
        $this->assertSame(3, $result->totalRequested);
        $this->assertSame(3, $result->returned);
    }

    // -------------------------------------------------------------------------
    // Scenario 2: Mix 2 valid + 1 invalid → 2 results, notFound=[invalid], no exception
    // -------------------------------------------------------------------------

    public function test_batch_mix_valid_and_invalid_no_exception(): void
    {
        $nodes = $this->makeEntrypointNodes();
        $flow = $this->buildFlow($nodes);

        $targets = [
            'App\\Http\\Controllers\\OrderController::store',
            'App\\Http\\Controllers\\OrderController::index',
            'App\\Http\\Controllers\\DoesNotExist::missing',
        ];

        $result = $this->builder->lookupProjectBatch('/project', $flow, 'node', $targets, 'summary', 1, 20);

        $this->assertCount(2, $result->results);
        $this->assertSame(['App\\Http\\Controllers\\DoesNotExist::missing'], $result->notFound);
        $this->assertFalse($result->truncated);
        $this->assertSame(3, $result->totalRequested);
        $this->assertSame(2, $result->returned);
    }

    // -------------------------------------------------------------------------
    // Scenario 3: targetType=area with 2 valid areas
    // -------------------------------------------------------------------------

    public function test_batch_area_type_with_two_valid_areas(): void
    {
        $nodes = $this->makeEntrypointNodes();
        $flow = $this->buildFlow($nodes);

        // areaNameForNode uses first 2 namespace segments: App\Http, App\Application
        $result = $this->builder->lookupProjectBatch('/project', $flow, 'area', [
            'App\\Http',
            'App\\Application',
        ], 'summary', 1, 20);

        $this->assertSame('lookup_batch_result', $result->kind);
        $this->assertSame('area', $result->targetType);
        $this->assertCount(2, $result->results);
        $this->assertSame([], $result->notFound);
    }

    // -------------------------------------------------------------------------
    // Scenario 4: limit=1 with 3 targets → 1 result, droppedTargets=2, truncated=true
    // -------------------------------------------------------------------------

    public function test_batch_limit_truncates_dropped_targets(): void
    {
        $nodes = $this->makeEntrypointNodes();
        $flow = $this->buildFlow($nodes);

        $targets = [
            'App\\Http\\Controllers\\OrderController::store',
            'App\\Http\\Controllers\\OrderController::index',
            'App\\Http\\Controllers\\UserController::index',
        ];

        $result = $this->builder->lookupProjectBatch('/project', $flow, 'node', $targets, 'summary', 1, 1);

        $this->assertCount(1, $result->results);
        $this->assertCount(2, $result->droppedTargets);
        $this->assertTrue($result->truncated);
        $this->assertSame(3, $result->totalRequested);
        $this->assertSame(1, $result->returned);
    }

    // -------------------------------------------------------------------------
    // Scenario 5: "all" with targetType=entrypoint → returns actionable entrypoints
    // -------------------------------------------------------------------------

    public function test_expand_all_entrypoints_returns_actionable_nodes(): void
    {
        $nodes = $this->makeEntrypointNodes();
        $flow = $this->buildFlow($nodes);

        // expandAllTargets should return the actionable entrypoints (Http controllers here)
        $expanded = $this->builder->expandAllTargets($flow, 'entrypoint');

        $this->assertIsArray($expanded);
        $this->assertNotEmpty($expanded);

        // All expanded targets should be valid node IDs in the flow
        foreach ($expanded as $target) {
            $this->assertNotNull($flow->node($target), "Target {$target} not found in flow");
        }
    }

    // -------------------------------------------------------------------------
    // Scenario 6: "all" with targetType=node → InvalidArgumentException mentioning flow_find
    // -------------------------------------------------------------------------

    public function test_expand_all_node_throws_exception_mentioning_flow_find(): void
    {
        $nodes = $this->makeEntrypointNodes();
        $flow = $this->buildFlow($nodes);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/flow_find/');

        $this->builder->expandAllTargets($flow, 'node');
    }

    // -------------------------------------------------------------------------
    // Scenario 7: "all" with targetType=path → InvalidArgumentException
    // -------------------------------------------------------------------------

    public function test_expand_all_path_throws_exception(): void
    {
        $nodes = $this->makeEntrypointNodes();
        $flow = $this->buildFlow($nodes);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/path/i');

        $this->builder->expandAllTargets($flow, 'path');
    }

    // -------------------------------------------------------------------------
    // Scenario 8: Order preserved between input and output
    // -------------------------------------------------------------------------

    public function test_batch_order_preserved_between_input_and_output(): void
    {
        $nodes = $this->makeEntrypointNodes();
        $flow = $this->buildFlow($nodes);

        $targets = [
            'App\\Http\\Controllers\\UserController::index',
            'App\\Http\\Controllers\\OrderController::store',
            'App\\Http\\Controllers\\OrderController::index',
        ];

        $result = $this->builder->lookupProjectBatch('/project', $flow, 'node', $targets, 'summary', 1, 20);

        $this->assertCount(3, $result->results);
        $this->assertSame('App\\Http\\Controllers\\UserController::index', $result->results[0]['target']);
        $this->assertSame('App\\Http\\Controllers\\OrderController::store', $result->results[1]['target']);
        $this->assertSame('App\\Http\\Controllers\\OrderController::index', $result->results[2]['target']);
    }

    // -------------------------------------------------------------------------
    // Scenario 9: Duplicate targets are deduplicated before processing
    // -------------------------------------------------------------------------

    public function test_batch_duplicates_are_deduplicated(): void
    {
        $nodes = $this->makeEntrypointNodes();
        $flow = $this->buildFlow($nodes);

        $targets = [
            'App\\Http\\Controllers\\OrderController::store',
            'App\\Http\\Controllers\\OrderController::store',  // duplicate
            'App\\Http\\Controllers\\OrderController::store',  // duplicate
        ];

        // 3 requested but 3 unique would be deduplicated to 1 unique
        $result = $this->builder->lookupProjectBatch('/project', $flow, 'node', $targets, 'summary', 1, 20);

        // After dedup, only 1 unique target remains
        $this->assertSame(1, $result->totalRequested);
        $this->assertCount(1, $result->results);
    }

    // -------------------------------------------------------------------------
    // Scenario P1: expandAllCatalogTargets with entrypoint returns aggregated list
    // -------------------------------------------------------------------------

    public function test_expand_all_catalog_targets_entrypoint_aggregates_across_services(): void
    {
        $f = $this->f();
        $nodesA = [
            new Node('App\\Http\\Controllers\\OrderController', 'store', $f, 10),
            new Node('App\\Http\\Controllers\\OrderController', 'index', $f, 20),
        ];
        $nodesB = [
            new Node('App\\Http\\Controllers\\UserController', 'index', $f, 30),
        ];

        $flowA = $this->buildFlow($nodesA);
        $flowB = $this->buildFlow($nodesB);

        $serviceA = new ServiceInfo(name: 'svc-a', root: '/a', flow: $flowA, files: []);
        $serviceB = new ServiceInfo(name: 'svc-b', root: '/b', flow: $flowB, files: []);

        $expanded = $this->builder->expandAllCatalogTargets([$serviceA, $serviceB], 'entrypoint');

        $this->assertIsArray($expanded);
        $this->assertNotEmpty($expanded);

        // All returned IDs must be valid node IDs in at least one of the flows
        $allNodeIds = array_merge(
            array_map(static fn(Node $n): string => $n->id(), $flowA->nodes()),
            array_map(static fn(Node $n): string => $n->id(), $flowB->nodes()),
        );
        foreach ($expanded as $id) {
            $this->assertContains($id, $allNodeIds, "Unexpected entrypoint ID: {$id}");
        }
    }

    // -------------------------------------------------------------------------
    // Scenario P2: invalid targetType in batch throws, not silently goes to notFound
    // -------------------------------------------------------------------------

    public function test_batch_invalid_target_type_throws_not_silently_not_found(): void
    {
        $nodes = $this->makeEntrypointNodes();
        $flow = $this->buildFlow($nodes);

        $this->expectException(\InvalidArgumentException::class);

        $this->builder->lookupProjectBatch('/project', $flow, 'service', [
            'App\\Http\\Controllers\\OrderController::store',
        ], 'summary', 1, 20);
    }

    // -------------------------------------------------------------------------
    // Finding 1: expandAllTargets boundary does NOT include external-http when no
    // outbound HTTP calls exist in the flow.
    // -------------------------------------------------------------------------

    public function test_expand_all_boundary_excludes_external_http_when_no_outbound_calls(): void
    {
        // Flow with only Http controllers — no http_call edges to external targets
        $nodes = $this->makeEntrypointNodes();
        $flow = $this->buildFlow($nodes);

        $boundaries = $this->builder->expandAllTargets($flow, 'boundary');

        $this->assertNotContains('external-http', $boundaries, 'external-http must not appear when the flow has no outbound HTTP edges');
    }

    // -------------------------------------------------------------------------
    // Finding 2: expandAllCatalogTargets boundary includes integration edge types
    // -------------------------------------------------------------------------

    public function test_expand_all_catalog_boundary_includes_integration_edge_types(): void
    {
        $f = $this->f();
        $nodesA = [new Node('App\\Http\\Controllers\\OrderController', 'store', $f, 10)];
        $flowA = $this->buildFlow($nodesA);
        $serviceA = new ServiceInfo(name: 'svc-a', root: '/a', flow: $flowA, files: []);

        $appmap = [
            'services' => [['name' => 'svc-a']],
            'serviceEdges' => [],
            'integrationEdges' => [
                ['fromService' => 'svc-a', 'toService' => 'svc-b', 'type' => 'grpc'],
                ['fromService' => 'svc-b', 'toService' => null, 'type' => 'message-queue'],
            ],
        ];

        $boundaries = $this->builder->expandAllCatalogTargets([$serviceA], 'boundary', $appmap);

        $this->assertContains('grpc', $boundaries, 'grpc from integrationEdges must appear in boundary list');
        $this->assertContains('message-queue', $boundaries, 'message-queue from integrationEdges must appear in boundary list');
    }
}
