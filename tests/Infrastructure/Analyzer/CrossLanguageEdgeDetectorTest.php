<?php

namespace Tests\Infrastructure\Analyzer;

use FlowEngine\Domain\Flow\DefaultNodeFactory;
use FlowEngine\Domain\Flow\Edge;
use FlowEngine\Domain\Flow\Node;
use FlowEngine\Infrastructure\Analyzer\CrossLanguageEdgeDetector;
use PHPUnit\Framework\TestCase;

final class CrossLanguageEdgeDetectorTest extends TestCase
{
    private string $tempDir;
    private DefaultNodeFactory $factory;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/cled-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
        $this->factory = new DefaultNodeFactory();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function createTempFile(string $name): string
    {
        $path = $this->tempDir . '/' . $name;
        file_put_contents($path, '<?php // stub');
        return $path;
    }

    private function makeNode(string $class, string $method, string $language, ?array $metadata = null): Node
    {
        $file = $this->createTempFile(uniqid('node_', true) . '.php');
        return $this->factory->create($class, $method, $file, 1, $language, $metadata);
    }

    // -------------------------------------------------------------------------

    public function test_resolves_fetch_url_to_php_node_with_matching_http_path(): void
    {
        $phpNode = $this->makeNode(
            'App\\Controller\\UserController',
            'index',
            'php',
            ['http_method' => 'GET', 'http_path' => '/api/users']
        );

        $tsNode = $this->makeNode('src.services.UserService', 'loadUsers', 'typescript');

        $virtualEdge = new Edge($tsNode->id(), 'http:GET:/api/users', 'fetch', 'http_call');

        $detector = new CrossLanguageEdgeDetector();
        $result   = $detector->detect([$phpNode, $tsNode], [$virtualEdge]);

        // Should have original + resolved edge
        self::assertCount(2, $result);

        $resolvedEdge = null;
        foreach ($result as $edge) {
            if ($edge->to() === $phpNode->id()) {
                $resolvedEdge = $edge;
            }
        }

        self::assertNotNull($resolvedEdge, 'Resolved edge to PHP node not found');
        self::assertSame($tsNode->id(), $resolvedEdge->from());
        self::assertSame('http_call', $resolvedEdge->type());
    }

    public function test_no_match_when_paths_differ(): void
    {
        $phpNode = $this->makeNode(
            'App\\Controller\\OrderController',
            'list',
            'php',
            ['http_method' => 'GET', 'http_path' => '/api/orders']
        );

        $tsNode = $this->makeNode('src.client.ApiClient', 'getUsers', 'typescript');

        $virtualEdge = new Edge($tsNode->id(), 'http:GET:/api/users', 'fetch', 'http_call');

        $detector = new CrossLanguageEdgeDetector();
        $result   = $detector->detect([$phpNode, $tsNode], [$virtualEdge]);

        // No match — result should be identical to input
        self::assertCount(1, $result);
        self::assertSame('http:GET:/api/users', $result[0]->to());
    }

    public function test_matches_with_path_parameter(): void
    {
        $phpNode = $this->makeNode(
            'App\\Controller\\UserController',
            'show',
            'php',
            ['http_method' => 'GET', 'http_path' => '/api/users/{id}']
        );

        $tsNode = $this->makeNode('src.services.UserService', 'getUserById', 'typescript');

        // The TS client calls a concrete URL — should match the parameterised route
        $virtualEdge = new Edge($tsNode->id(), 'http:GET:/api/users/123', 'fetch', 'http_call');

        $detector = new CrossLanguageEdgeDetector();
        $result   = $detector->detect([$phpNode, $tsNode], [$virtualEdge]);

        self::assertCount(2, $result);

        $resolvedEdge = null;
        foreach ($result as $edge) {
            if ($edge->to() === $phpNode->id()) {
                $resolvedEdge = $edge;
            }
        }

        self::assertNotNull($resolvedEdge, 'Resolved edge not found for parameterised route');
    }

    public function test_returns_both_virtual_and_resolved_edges(): void
    {
        $phpNode = $this->makeNode(
            'App\\Controller\\ProductController',
            'index',
            'php',
            ['http_method' => 'GET', 'http_path' => '/api/products']
        );

        $tsNode = $this->makeNode('src.api.ProductApi', 'fetchProducts', 'typescript');

        $virtualEdge = new Edge($tsNode->id(), 'http:GET:/api/products', 'fetch', 'http_call');

        $detector = new CrossLanguageEdgeDetector();
        $result   = $detector->detect([$phpNode, $tsNode], [$virtualEdge]);

        self::assertCount(2, $result, 'Should have both the virtual edge and the resolved real edge');

        $tos = array_map(fn($e) => $e->to(), $result);
        self::assertContains('http:GET:/api/products', $tos, 'Virtual edge should be kept');
        self::assertContains($phpNode->id(), $tos, 'Resolved real edge should be added');
    }

    // ── TypeScript import resolution ─────────────────────────────────────────

    public function test_resolves_ts_import_virtual_edge_for_function_export(): void
    {
        // Target: an exported function in another TS module
        $targetNode = $this->makeNode('src.shared.lib.api-client', 'getMetrics', 'typescript');
        $callerNode = $this->makeNode('src.app.page', 'Page', 'typescript');

        $virtualEdge = new Edge(
            $callerNode->id(),
            'ts_import:src.shared.lib.api-client::getMetrics',
            'getMetrics',
            'import_call'
        );

        $detector = new CrossLanguageEdgeDetector();
        $result   = $detector->detect([$targetNode, $callerNode], [$virtualEdge]);

        // Original virtual edge + resolved real edge
        self::assertCount(2, $result);

        $resolvedEdge = null;
        foreach ($result as $edge) {
            if ($edge->to() === $targetNode->id()) {
                $resolvedEdge = $edge;
            }
        }

        self::assertNotNull($resolvedEdge, 'Resolved edge to target function node not found');
        self::assertSame($callerNode->id(), $resolvedEdge->from());
        self::assertSame('import_call', $resolvedEdge->type());
    }

    public function test_resolves_ts_import_virtual_edge_for_class(): void
    {
        // Target: a class method — import { UserService } from './services'
        $classMethodNode = $this->makeNode('src.services.UserService', 'getUser', 'typescript');
        $callerNode      = $this->makeNode('src.app.page', 'Page', 'typescript');

        // The virtual edge uses "{parentModule}::{ClassName}" format
        $virtualEdge = new Edge(
            $callerNode->id(),
            'ts_import:src.services::UserService',
            'UserService',
            'import_call'
        );

        $detector = new CrossLanguageEdgeDetector();
        $result   = $detector->detect([$classMethodNode, $callerNode], [$virtualEdge]);

        self::assertCount(2, $result);

        $resolvedEdge = null;
        foreach ($result as $edge) {
            if ($edge->to() === $classMethodNode->id()) {
                $resolvedEdge = $edge;
            }
        }

        self::assertNotNull($resolvedEdge, 'Resolved edge to class method node not found');
        self::assertSame('import_call', $resolvedEdge->type());
    }

    public function test_keeps_virtual_edge_when_import_target_not_found(): void
    {
        $callerNode  = $this->makeNode('src.app.page', 'Page', 'typescript');
        $virtualEdge = new Edge(
            $callerNode->id(),
            'ts_import:src.unknown.module::missingFn',
            'missingFn',
            'import_call'
        );

        $detector = new CrossLanguageEdgeDetector();
        $result   = $detector->detect([$callerNode], [$virtualEdge]);

        // Only the original virtual edge — no resolved edge added
        self::assertCount(1, $result);
        self::assertSame('ts_import:src.unknown.module::missingFn', $result[0]->to());
    }

    public function test_php_nodes_are_excluded_from_import_index(): void
    {
        // PHP node with same naming pattern — must not be confused with TS modules
        $phpNode    = $this->makeNode('src.services.Foo', 'bar', 'php');
        $callerNode = $this->makeNode('src.app.page', 'Page', 'typescript');

        $virtualEdge = new Edge(
            $callerNode->id(),
            'ts_import:src.services::Foo',
            'Foo',
            'import_call'
        );

        $detector = new CrossLanguageEdgeDetector();
        $result   = $detector->detect([$phpNode, $callerNode], [$virtualEdge]);

        // No resolution because PHP nodes are not in the import index
        self::assertCount(1, $result);
    }
}
