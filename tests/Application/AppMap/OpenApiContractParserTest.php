<?php

namespace Tests\Application\AppMap;

use FlowEngine\Application\AppMap\OpenApiContractParser;
use PHPUnit\Framework\TestCase;

final class OpenApiContractParserTest extends TestCase
{
    private OpenApiContractParser $parser;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->parser = new OpenApiContractParser();
        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'flow-engine-openapi-' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tmpDir . DIRECTORY_SEPARATOR . '*') ?: []);
        @rmdir($this->tmpDir);
    }

    // -------------------------------------------------------------------------
    // Missing / empty file
    // -------------------------------------------------------------------------

    public function test_returns_empty_for_missing_file(): void
    {
        $result = $this->parser->parse('/nonexistent/openapi.yaml');

        $this->assertSame([], $result);
    }

    public function test_returns_empty_for_empty_file(): void
    {
        $path = $this->write('empty.json', '');

        $this->assertSame([], $this->parser->parse($path));
    }

    // -------------------------------------------------------------------------
    // JSON — OpenAPI 3.x
    // -------------------------------------------------------------------------

    public function test_parses_json_spec_endpoints(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'paths' => [
                '/users' => [
                    'get'  => ['summary' => 'List users'],
                    'post' => ['summary' => 'Create user'],
                ],
                '/users/{id}' => [
                    'get'    => ['summary' => 'Get user'],
                    'delete' => ['summary' => 'Delete user'],
                ],
            ],
        ];

        $path   = $this->write('spec.json', json_encode($spec));
        $result = $this->parser->parse($path);

        $this->assertCount(4, $result);
        $this->assertContains(['method' => 'GET',    'path' => '/users',       'summary' => 'List users'],   $result);
        $this->assertContains(['method' => 'POST',   'path' => '/users',       'summary' => 'Create user'],  $result);
        $this->assertContains(['method' => 'GET',    'path' => '/users/{id}',  'summary' => 'Get user'],     $result);
        $this->assertContains(['method' => 'DELETE', 'path' => '/users/{id}',  'summary' => 'Delete user'],  $result);
    }

    public function test_json_falls_back_to_description_when_no_summary(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'paths' => [
                '/health' => [
                    'get' => ['description' => 'Health check endpoint'],
                ],
            ],
        ];

        $path   = $this->write('spec.json', json_encode($spec));
        $result = $this->parser->parse($path);

        $this->assertSame('Health check endpoint', $result[0]['summary']);
    }

    public function test_json_falls_back_to_operation_id(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'paths' => [
                '/ping' => [
                    'get' => ['operationId' => 'pingSystem'],
                ],
            ],
        ];

        $path   = $this->write('spec.json', json_encode($spec));
        $result = $this->parser->parse($path);

        $this->assertSame('pingSystem', $result[0]['summary']);
    }

    public function test_json_skips_non_verb_path_item_keys(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'paths' => [
                '/items' => [
                    'get'        => ['summary' => 'List'],
                    'parameters' => [['in' => 'query', 'name' => 'q']],
                    'summary'    => 'Item operations',
                ],
            ],
        ];

        $path   = $this->write('spec.json', json_encode($spec));
        $result = $this->parser->parse($path);

        $this->assertCount(1, $result);
        $this->assertSame('GET', $result[0]['method']);
    }

    public function test_json_returns_empty_when_no_paths_key(): void
    {
        $spec = ['openapi' => '3.0.0', 'info' => ['title' => 'Empty']];

        $path   = $this->write('spec.json', json_encode($spec));
        $result = $this->parser->parse($path);

        $this->assertSame([], $result);
    }

    // -------------------------------------------------------------------------
    // YAML — OpenAPI 3.x
    // -------------------------------------------------------------------------

    public function test_parses_yaml_spec_endpoints(): void
    {
        $yaml = <<<YAML
openapi: "3.0.0"
info:
  title: Test API
paths:
  /users:
    get:
      summary: List users
    post:
      summary: Create user
  /users/{id}:
    get:
      summary: Get user
    delete:
      summary: Delete user
YAML;

        $path   = $this->write('spec.yaml', $yaml);
        $result = $this->parser->parse($path);

        $this->assertCount(4, $result);
        $this->assertContains(['method' => 'GET',    'path' => '/users',       'summary' => 'List users'],  $result);
        $this->assertContains(['method' => 'POST',   'path' => '/users',       'summary' => 'Create user'], $result);
        $this->assertContains(['method' => 'GET',    'path' => '/users/{id}',  'summary' => 'Get user'],    $result);
        $this->assertContains(['method' => 'DELETE', 'path' => '/users/{id}',  'summary' => 'Delete user'], $result);
    }

    public function test_yaml_handles_spec_without_summary(): void
    {
        $yaml = <<<YAML
openapi: "3.0.0"
paths:
  /status:
    get:
      responses:
        '200':
          description: OK
YAML;

        $path   = $this->write('spec.yaml', $yaml);
        $result = $this->parser->parse($path);

        $this->assertCount(1, $result);
        $this->assertSame('GET',     $result[0]['method']);
        $this->assertSame('/status', $result[0]['path']);
        $this->assertSame('',        $result[0]['summary']);
    }

    public function test_yaml_stops_at_next_root_level_key(): void
    {
        $yaml = <<<YAML
openapi: "3.0.0"
paths:
  /users:
    get:
      summary: List users
components:
  schemas:
    User:
      type: object
YAML;

        $path   = $this->write('spec.yaml', $yaml);
        $result = $this->parser->parse($path);

        $this->assertCount(1, $result);
    }

    public function test_yaml_returns_empty_when_no_paths_block(): void
    {
        $yaml = "openapi: \"3.0.0\"\ninfo:\n  title: No paths\n";

        $path   = $this->write('spec.yaml', $yaml);
        $result = $this->parser->parse($path);

        $this->assertSame([], $result);
    }

    // -------------------------------------------------------------------------
    // ApplicationMapBuilder integration
    // -------------------------------------------------------------------------

    public function test_builder_reports_contract_endpoint_not_in_code(): void
    {
        // Contract declares GET /orders — code exposes nothing
        $spec = [
            'openapi' => '3.0.0',
            'paths'   => ['/orders' => ['get' => ['summary' => 'List orders']]],
        ];
        $path = $this->write('spec.json', json_encode($spec));

        $contractEndpoints = $this->parser->parse($path);

        $flow    = new \FlowEngine\Domain\Flow\Flow([], []);
        $service = new \FlowEngine\Application\AppMap\ServiceInfo(
            name: 'api',
            root: '/tmp',
            flow: $flow,
            files: [],
            hostnames: [],
            contractEndpoints: $contractEndpoints,
        );

        $map = (new \FlowEngine\Application\AppMap\ApplicationMapBuilder())->build([$service]);

        $types = array_column($map['inconsistencies'], 'type');
        $this->assertContains('CONTRACT_ENDPOINT_NOT_IN_CODE', $types);
    }

    public function test_builder_reports_code_endpoint_not_in_contract(): void
    {
        // Code exposes POST /users — contract declares nothing
        $spec = ['openapi' => '3.0.0', 'paths' => []];
        $path = $this->write('spec.json', json_encode($spec));

        $contractEndpoints = $this->parser->parse($path);

        $node = new \FlowEngine\Domain\Flow\Node(
            'App\UserController', 'store', __FILE__, 1, 'php',
            ['http_method' => 'POST', 'http_path' => '/users']
        );
        $flow    = new \FlowEngine\Domain\Flow\Flow([$node], []);
        $service = new \FlowEngine\Application\AppMap\ServiceInfo(
            name: 'api',
            root: '/tmp',
            flow: $flow,
            files: [],
            hostnames: [],
            contractEndpoints: $contractEndpoints,
        );

        $map = (new \FlowEngine\Application\AppMap\ApplicationMapBuilder())->build([$service]);

        $types = array_column($map['inconsistencies'], 'type');
        $this->assertContains('CODE_ENDPOINT_NOT_IN_CONTRACT', $types);
    }

    public function test_builder_no_inconsistencies_when_endpoints_match(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'paths'   => ['/users' => ['get' => ['summary' => 'List users']]],
        ];
        $path = $this->write('spec.json', json_encode($spec));

        $contractEndpoints = $this->parser->parse($path);

        $node = new \FlowEngine\Domain\Flow\Node(
            'App\UserController', 'index', __FILE__, 1, 'php',
            ['http_method' => 'GET', 'http_path' => '/users']
        );
        $flow    = new \FlowEngine\Domain\Flow\Flow([$node], []);
        $service = new \FlowEngine\Application\AppMap\ServiceInfo(
            name: 'api',
            root: '/tmp',
            flow: $flow,
            files: [],
            hostnames: [],
            contractEndpoints: $contractEndpoints,
        );

        $map = (new \FlowEngine\Application\AppMap\ApplicationMapBuilder())->build([$service]);

        $this->assertSame([], $map['inconsistencies']);
    }

    public function test_builder_normalises_path_params_for_comparison(): void
    {
        // Contract uses {id}, code uses {userId} — should still match
        $spec = [
            'openapi' => '3.0.0',
            'paths'   => ['/users/{id}' => ['get' => ['summary' => 'Get user']]],
        ];
        $path = $this->write('spec.json', json_encode($spec));

        $contractEndpoints = $this->parser->parse($path);

        $node = new \FlowEngine\Domain\Flow\Node(
            'App\UserController', 'show', __FILE__, 1, 'php',
            ['http_method' => 'GET', 'http_path' => '/users/{userId}']
        );
        $flow    = new \FlowEngine\Domain\Flow\Flow([$node], []);
        $service = new \FlowEngine\Application\AppMap\ServiceInfo(
            name: 'api',
            root: '/tmp',
            flow: $flow,
            files: [],
            hostnames: [],
            contractEndpoints: $contractEndpoints,
        );

        $map = (new \FlowEngine\Application\AppMap\ApplicationMapBuilder())->build([$service]);

        $this->assertSame([], $map['inconsistencies']);
    }

    public function test_builder_skips_contract_check_when_no_contract_given(): void
    {
        // No contractEndpoints — no inconsistencies from contract checking
        $node = new \FlowEngine\Domain\Flow\Node(
            'App\UserController', 'index', __FILE__, 1, 'php',
            ['http_method' => 'GET', 'http_path' => '/users']
        );
        $flow    = new \FlowEngine\Domain\Flow\Flow([$node], []);
        $service = new \FlowEngine\Application\AppMap\ServiceInfo(
            name: 'api', root: '/tmp', flow: $flow, files: [],
        );

        $map = (new \FlowEngine\Application\AppMap\ApplicationMapBuilder())->build([$service]);

        $this->assertSame([], $map['inconsistencies']);
    }

    public function test_builder_reports_contract_method_set_mismatch_for_same_path(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'paths' => [
                '/users' => [
                    'get' => ['summary' => 'List users'],
                    'post' => ['summary' => 'Create user'],
                ],
            ],
        ];
        $path = $this->write('spec.json', json_encode($spec));
        $contractEndpoints = $this->parser->parse($path);

        $node = new \FlowEngine\Domain\Flow\Node(
            'App\UserController', 'index', __FILE__, 1, 'php',
            ['http_method' => 'GET', 'http_path' => '/users']
        );
        $flow = new \FlowEngine\Domain\Flow\Flow([$node], []);
        $service = new \FlowEngine\Application\AppMap\ServiceInfo(
            name: 'api',
            root: '/tmp',
            flow: $flow,
            files: [],
            hostnames: [],
            contractEndpoints: $contractEndpoints,
        );

        $map = (new \FlowEngine\Application\AppMap\ApplicationMapBuilder())->build([$service]);

        $types = array_column($map['inconsistencies'], 'type');
        $this->assertContains('CONTRACT_METHOD_SET_MISMATCH', $types);
    }

    public function test_builder_reports_duplicate_contract_endpoint(): void
    {
        $contractEndpoints = [
            ['method' => 'GET', 'path' => '/users', 'summary' => 'List users'],
            ['method' => 'GET', 'path' => '/users', 'summary' => 'List users duplicate'],
        ];

        $flow = new \FlowEngine\Domain\Flow\Flow([], []);
        $service = new \FlowEngine\Application\AppMap\ServiceInfo(
            name: 'api',
            root: '/tmp',
            flow: $flow,
            files: [],
            hostnames: [],
            contractEndpoints: $contractEndpoints,
        );

        $map = (new \FlowEngine\Application\AppMap\ApplicationMapBuilder())->build([$service]);

        $types = array_column($map['inconsistencies'], 'type');
        $this->assertContains('CONTRACT_DUPLICATE_ENDPOINT', $types);
    }

    public function test_builder_reports_duplicate_code_endpoint(): void
    {
        $contractEndpoints = [
            ['method' => 'GET', 'path' => '/users', 'summary' => 'List users'],
        ];

        $nodeA = new \FlowEngine\Domain\Flow\Node(
            'App\UserController', 'index', __FILE__, 1, 'php',
            ['http_method' => 'GET', 'http_path' => '/users']
        );
        $nodeB = new \FlowEngine\Domain\Flow\Node(
            'App\AdminUserController', 'index', __FILE__, 2, 'php',
            ['http_method' => 'GET', 'http_path' => '/users']
        );
        $flow = new \FlowEngine\Domain\Flow\Flow([$nodeA, $nodeB], []);
        $service = new \FlowEngine\Application\AppMap\ServiceInfo(
            name: 'api',
            root: '/tmp',
            flow: $flow,
            files: [],
            hostnames: [],
            contractEndpoints: $contractEndpoints,
        );

        $map = (new \FlowEngine\Application\AppMap\ApplicationMapBuilder())->build([$service]);

        $types = array_column($map['inconsistencies'], 'type');
        $this->assertContains('CODE_DUPLICATE_ENDPOINT', $types);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function write(string $filename, string $content): string
    {
        $path = $this->tmpDir . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($path, $content);
        return $path;
    }
}
