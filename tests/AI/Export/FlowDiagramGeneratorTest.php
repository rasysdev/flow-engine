<?php

namespace FlowEngine\Tests\AI\Export;

use FlowEngine\AI\Export\FlowDiagramGenerator;
use FlowEngine\Domain\Flow\Edge;
use FlowEngine\Domain\Flow\Flow;
use FlowEngine\Domain\Flow\FlowTracer;
use FlowEngine\Domain\Flow\Node;
use PHPUnit\Framework\TestCase;

final class FlowDiagramGeneratorTest extends TestCase
{
    private FlowDiagramGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new FlowDiagramGenerator();
    }

    // -------------------------------------------------------------------------
    // flowchart
    // -------------------------------------------------------------------------

    public function test_flowchart_contains_entrypoint_node(): void
    {
        $nodes = [
            new Node('App\Http\UserController', 'index', __FILE__, 1),
            new Node('App\Service\UserService', 'all', __FILE__, 2),
        ];
        $edges = [
            new Edge('App\Http\UserController::index', 'App\Service\UserService::all', 'all', 'method_call'),
        ];

        $flow    = new Flow($nodes, $edges);
        $tracer  = new FlowTracer($flow);
        $diagram = $this->generator->flowchart($flow, $tracer, 'App\Http\UserController::index');

        $this->assertStringStartsWith('flowchart LR', $diagram);
        $this->assertStringContainsString('classDef entry', $diagram);
        // Both nodes should be present (labels contain method names)
        $this->assertStringContainsString('index()', $diagram);
        $this->assertStringContainsString('all()', $diagram);
        // Edge should be present
        $this->assertStringContainsString('-->', $diagram);
    }

    public function test_flowchart_unknown_entrypoint_throws(): void
    {
        $flow   = new Flow([], []);
        $tracer = new FlowTracer($flow);

        $this->expectException(\InvalidArgumentException::class);
        $this->generator->flowchart($flow, $tracer, 'Unknown::method');
    }

    public function test_flowchart_excludes_property_access_edges(): void
    {
        $nodes = [
            new Node('App\Service', 'doWork', __FILE__, 1),
        ];
        $edges = [
            new Edge('App\Service::doWork', 'App\Service::$repo', '$repo', 'property_access'),
        ];

        $flow    = new Flow($nodes, $edges);
        $tracer  = new FlowTracer($flow);
        $diagram = $this->generator->flowchart($flow, $tracer, 'App\Service::doWork');

        // property_access edges are excluded from flowchart
        $this->assertStringNotContainsString('-->', $diagram);
    }

    public function test_flowchart_scopes_to_depth(): void
    {
        // Chain: A → B → C → D
        $nodes = [
            new Node('A', 'a', __FILE__, 1),
            new Node('B', 'b', __FILE__, 2),
            new Node('C', 'c', __FILE__, 3),
            new Node('D', 'd', __FILE__, 4),
        ];
        $edges = [
            new Edge('A::a', 'B::b', 'b', 'method_call'),
            new Edge('B::b', 'C::c', 'c', 'method_call'),
            new Edge('C::c', 'D::d', 'd', 'method_call'),
        ];

        $flow    = new Flow($nodes, $edges);
        $tracer  = new FlowTracer($flow);
        $diagram = $this->generator->flowchart($flow, $tracer, 'A::a', 2);

        // depth=2 from A: includes A, B, C — not D
        $this->assertStringContainsString('a()', $diagram);
        $this->assertStringContainsString('b()', $diagram);
        $this->assertStringContainsString('c()', $diagram);
        $this->assertStringNotContainsString('d()', $diagram);
    }

    // -------------------------------------------------------------------------
    // classDiagram
    // -------------------------------------------------------------------------

    public function test_class_diagram_starts_with_header(): void
    {
        $flow    = new Flow([], []);
        $diagram = $this->generator->classDiagram($flow);

        $this->assertStringStartsWith('classDiagram', $diagram);
    }

    public function test_class_diagram_groups_methods_by_class(): void
    {
        $nodes = [
            new Node('App\User', 'getName', __FILE__, 1),
            new Node('App\User', 'setName', __FILE__, 2),
            new Node('App\Order', 'total', __FILE__, 3),
        ];

        $flow    = new Flow($nodes, []);
        $diagram = $this->generator->classDiagram($flow);

        $this->assertStringContainsString('+getName()', $diagram);
        $this->assertStringContainsString('+setName()', $diagram);
        $this->assertStringContainsString('+total()', $diagram);
    }

    public function test_class_diagram_namespace_filter(): void
    {
        $nodes = [
            new Node('App\Http\Controller', 'index', __FILE__, 1),
            new Node('App\Domain\Service', 'run', __FILE__, 2),
        ];

        $flow    = new Flow($nodes, []);
        $diagram = $this->generator->classDiagram($flow, 'App\Http');

        $this->assertStringContainsString('index()', $diagram);
        $this->assertStringNotContainsString('run()', $diagram);
    }

    public function test_class_diagram_includes_trait_edge(): void
    {
        $nodes = [
            new Node('App\Service', 'process', __FILE__, 1),
        ];
        $edges = [
            new Edge('App\Service::__construct', 'App\LoggerTrait::__trait', '__trait', 'trait_usage'),
        ];

        $flow    = new Flow($nodes, $edges);
        $diagram = $this->generator->classDiagram($flow);

        $this->assertStringContainsString('..|>', $diagram);
    }

    // -------------------------------------------------------------------------
    // componentDiagram
    // -------------------------------------------------------------------------

    public function test_component_diagram_starts_with_graph_lr(): void
    {
        $flow    = new Flow([], []);
        $diagram = $this->generator->componentDiagram($flow);

        $this->assertStringStartsWith('graph LR', $diagram);
    }

    public function test_component_diagram_creates_subgraph_per_namespace(): void
    {
        $nodes = [
            new Node('App\Http\Controller', 'index', __FILE__, 1),
            new Node('App\Domain\Service', 'run', __FILE__, 2),
        ];

        $flow    = new Flow($nodes, []);
        $diagram = $this->generator->componentDiagram($flow);

        $this->assertStringContainsString('subgraph', $diagram);
        $this->assertStringContainsString('App\\Http', $diagram);
        $this->assertStringContainsString('App\\Domain', $diagram);
    }

    public function test_component_diagram_shows_cross_namespace_edges(): void
    {
        $nodes = [
            new Node('App\Http\Controller', 'index', __FILE__, 1),
            new Node('App\Domain\Service', 'run', __FILE__, 2),
        ];
        $edges = [
            new Edge('App\Http\Controller::index', 'App\Domain\Service::run', 'run', 'method_call'),
        ];

        $flow    = new Flow($nodes, $edges);
        $diagram = $this->generator->componentDiagram($flow);

        // An edge between the two components must exist
        $this->assertStringContainsString('-->', $diagram);
    }

    public function test_component_diagram_deduplicates_cross_namespace_edges(): void
    {
        $nodes = [
            new Node('App\Http\ControllerA', 'a', __FILE__, 1),
            new Node('App\Http\ControllerB', 'b', __FILE__, 2),
            new Node('App\Domain\Service', 'run', __FILE__, 3),
        ];
        $edges = [
            new Edge('App\Http\ControllerA::a', 'App\Domain\Service::run', 'run', 'method_call'),
            new Edge('App\Http\ControllerB::b', 'App\Domain\Service::run', 'run', 'method_call'),
        ];

        $flow    = new Flow($nodes, $edges);
        $diagram = $this->generator->componentDiagram($flow);

        // The same component-level edge should appear only once
        $this->assertSame(1, substr_count($diagram, 'c_App_Http --> c_App_Domain'));
    }

    public function test_component_diagram_namespace_filter(): void
    {
        $nodes = [
            new Node('App\Http\Controller', 'index', __FILE__, 1),
            new Node('Infrastructure\Repo', 'find', __FILE__, 2),
        ];

        $flow    = new Flow($nodes, []);
        $diagram = $this->generator->componentDiagram($flow, 'App');

        $this->assertStringContainsString('App', $diagram);
        $this->assertStringNotContainsString('Infrastructure', $diagram);
    }

    // -------------------------------------------------------------------------
    // c4Context
    // -------------------------------------------------------------------------

    public function test_c4context_starts_with_c4context_keyword(): void
    {
        $flow    = new Flow([], []);
        $diagram = $this->generator->c4Context($flow, 'MyProject');

        $this->assertStringStartsWith('C4Context', $diagram);
    }

    public function test_c4context_includes_system_boundary_and_system(): void
    {
        $flow    = new Flow([], []);
        $diagram = $this->generator->c4Context($flow, 'MyProject');

        $this->assertStringContainsString('System_Boundary(', $diagram);
        $this->assertStringContainsString('System(', $diagram);
        $this->assertStringContainsString('MyProject', $diagram);
    }

    public function test_c4context_adds_http_client_when_http_entrypoints_exist(): void
    {
        $nodes = [
            new Node('App\Http\Controller', 'index', __FILE__, 1, 'php', [
                'http_method' => 'GET',
                'http_path'   => '/users',
            ]),
        ];

        $flow    = new Flow($nodes, []);
        $diagram = $this->generator->c4Context($flow, 'MyProject');

        $this->assertStringContainsString('Person(httpClient', $diagram);
        $this->assertStringContainsString('Rel(httpClient', $diagram);
    }

    public function test_c4context_adds_http_client_for_typescript_route_handlers(): void
    {
        // Next.js / TypeScript route handlers export functions named after HTTP verbs
        $nodes = [
            new Node('src.app.api.users.route', 'GET', __FILE__, 1, 'typescript'),
            new Node('src.app.api.users.route', 'POST', __FILE__, 5, 'typescript'),
        ];

        $flow    = new Flow($nodes, []);
        $diagram = $this->generator->c4Context($flow, 'MyApp');

        $this->assertStringContainsString('Person(httpClient', $diagram);
        $this->assertStringContainsString('Rel(httpClient', $diagram);
    }

    public function test_c4context_adds_cli_user_when_cli_entrypoints_exist(): void
    {
        $nodes = [
            new Node('App\Command\Sync', 'handle', __FILE__, 1, 'php', [
                'entrypoint_type' => 'cli',
            ]),
        ];

        $flow    = new Flow($nodes, []);
        $diagram = $this->generator->c4Context($flow, 'MyProject');

        $this->assertStringContainsString('Person(cliUser', $diagram);
        $this->assertStringContainsString('Rel(cliUser', $diagram);
    }

    public function test_c4context_no_actors_when_no_entrypoints(): void
    {
        $nodes = [
            new Node('App\Service\Util', 'helper', __FILE__, 1),
        ];

        $flow    = new Flow($nodes, []);
        $diagram = $this->generator->c4Context($flow, 'MyProject');

        $this->assertStringNotContainsString('Person(', $diagram);
    }

    public function test_c4context_detects_external_https_host_from_unresolved_http_call(): void
    {
        $nodes = [
            new Node('App\Service', 'call', __FILE__, 1),
        ];
        // Virtual unresolved http_call edge (to does not map to a real node)
        $edges = [
            new Edge('App\Service::call', 'http:GET:https://payments.example.test/v1/charges', 'fetch', 'http_call'),
        ];

        $flow    = new Flow($nodes, $edges);
        $diagram = $this->generator->c4Context($flow, 'MyProject');

        $this->assertStringContainsString('System_Ext(', $diagram);
        $this->assertStringContainsString('payments.example.test', $diagram);
    }

    public function test_c4context_skips_relative_url_http_calls(): void
    {
        $nodes = [
            new Node('App\Service', 'call', __FILE__, 1),
        ];
        // Relative path — internal route, should not generate a System_Ext
        $edges = [
            new Edge('App\Service::call', 'http:GET:/api/users', 'fetch', 'http_call'),
        ];

        $flow    = new Flow($nodes, $edges);
        $diagram = $this->generator->c4Context($flow, 'MyProject');

        $this->assertStringNotContainsString('System_Ext(', $diagram);
    }

    public function test_c4context_skips_resolved_http_call_edges(): void
    {
        // An http_call edge whose `to` resolves to a real node should NOT produce a System_Ext
        $nodes = [
            new Node('App\Frontend', 'fetch', __FILE__, 1),
            new Node('App\Http\Controller', 'index', __FILE__, 2),
        ];
        $edges = [
            new Edge('App\Frontend::fetch', 'App\Http\Controller::index', 'index', 'http_call'),
        ];

        $flow    = new Flow($nodes, $edges);
        $diagram = $this->generator->c4Context($flow, 'MyProject');

        $this->assertStringNotContainsString('System_Ext(', $diagram);
    }

    public function test_c4context_includes_language_in_system_description(): void
    {
        $nodes = [
            new Node('App\Service', 'run', __FILE__, 1, 'python'),
        ];

        $flow    = new Flow($nodes, []);
        $diagram = $this->generator->c4Context($flow, 'PythonService');

        $this->assertStringContainsString('python', $diagram);
    }

    public function test_c4context_deduplicates_external_hosts(): void
    {
        $nodes = [
            new Node('App\Service', 'call', __FILE__, 1),
        ];
        // Two edges to the same host
        $edges = [
            new Edge('App\Service::call', 'http:GET:https://payments.example.test/charges', 'fetch', 'http_call'),
            new Edge('App\Service::call', 'http:POST:https://payments.example.test/refunds', 'fetch', 'http_call'),
        ];

        $flow    = new Flow($nodes, $edges);
        $diagram = $this->generator->c4Context($flow, 'MyProject');

        // payments.example.test should appear only once as a System_Ext
        $this->assertSame(1, substr_count($diagram, 'payments.example.test'));
    }
}
