<?php

namespace Tests\Infrastructure\Analyzer;

use FlowEngine\Domain\Flow\DefaultNodeFactory;
use FlowEngine\Infrastructure\Analyzer\AstParser;
use PHPUnit\Framework\TestCase;

final class AstParserPropertyAccessTest extends TestCase
{
    private AstParser $parser;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->parser = new AstParser(new DefaultNodeFactory());
        $this->tempDir = sys_get_temp_dir() . '/flow-engine-test-' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tempDir . '/*.php'));
        rmdir($this->tempDir);
    }

    public function test_detects_property_access_on_this(): void
    {
        $file = $this->createTempFile('<?php
namespace App;
class Service {
    private $repository;
    
    public function execute() {
        return $this->repository;
    }
}
');

        $result = $this->parser->parse($file);

        $this->assertCount(1, $result['nodes']);
        $this->assertCount(1, $result['edges']);

        $edge = $result['edges'][0];
        $this->assertEquals('App\Service::execute', $edge->from());
        $this->assertEquals('App\Service::$repository', $edge->to());
        $this->assertEquals('$repository', $edge->method());
    }

    public function test_detects_chained_property_and_method(): void
    {
        $file = $this->createTempFile('<?php
namespace App;
class Controller {
    private $service;
    
    public function handle() {
        return $this->service->execute();
    }
}
');

        $result = $this->parser->parse($file);

        $this->assertCount(1, $result['nodes']);

        // Deve detectar:
        // 1. $this->service (property access)
        // 2. $service->execute() (method call)
        $this->assertGreaterThanOrEqual(2, count($result['edges']));

        $edgeMethods = array_map(fn($e) => $e->method(), $result['edges']);
        $this->assertContains('$service', $edgeMethods);
        $this->assertContains('execute', $edgeMethods);
    }

    public function test_detects_static_property_access(): void
    {
        $file = $this->createTempFile('<?php
namespace App;
class Config {
    public static $instance;
}
class Service {
    public function get() {
        return Config::$instance;
    }
}
');

        $result = $this->parser->parse($file);

        $edges = array_filter(
            $result['edges'],
            fn($e) => str_contains($e->to(), '$instance')
        );

        $this->assertGreaterThan(0, count($edges));

        $edge = reset($edges);
        $this->assertEquals('App\Service::get', $edge->from());
        $this->assertEquals('App\Config::$instance', $edge->to());
    }

    public function test_ignores_dynamic_property_access(): void
    {
        $file = $this->createTempFile('<?php
namespace App;
class Dynamic {
    public function get($prop) {
        return $this->$prop; // property dinâmica
    }
}
');

        $result = $this->parser->parse($file);

        // Não deve criar edge pra property dinâmica
        $this->assertCount(0, $result['edges']);
    }

    public function test_multiple_property_accesses_in_same_method(): void
    {
        $file = $this->createTempFile('<?php
namespace App;
class Repository {
    private $connection;
    private $cache;
    
    public function find() {
        $data = $this->connection;
        $cached = $this->cache;
        return $data;
    }
}
');

        $result = $this->parser->parse($file);

        // Deve detectar ambos property accesses
        $this->assertGreaterThanOrEqual(2, count($result['edges']));

        $targets = array_map(fn($e) => $e->to(), $result['edges']);
        $this->assertContains('App\Repository::$connection', $targets);
        $this->assertContains('App\Repository::$cache', $targets);
    }

    public function test_property_chain_resolution(): void
    {
        $file = $this->createTempFile('<?php
namespace App;
class Chain {
    private $service;
    
    public function run() {
        return $this->service->property->method();
    }
}
');

        $result = $this->parser->parse($file);

        // Deve detectar a cadeia de property access
        $this->assertGreaterThan(0, count($result['edges']));

        // Pelo menos $service deve ser detectado
        $edgeMethods = array_map(fn($e) => $e->method(), $result['edges']);
        $this->assertContains('$service', $edgeMethods);
    }

    public function test_combined_with_existing_method_call_detection(): void
    {
        $file = $this->createTempFile('<?php
namespace App;
class Combined {
    private $repo;
    
    public function process() {
        $data = $this->repo;
        $this->validate();
        return $data;
    }
    
    private function validate() {}
}
');

        $result = $this->parser->parse($file);

        $this->assertCount(2, $result['nodes']); // process + validate

        // Deve detectar: property access ($repo) + method call (validate)
        $this->assertGreaterThanOrEqual(2, count($result['edges']));
    }

    private function createTempFile(string $content): string
    {
        $file = $this->tempDir . '/' . uniqid('test_', true) . '.php';
        file_put_contents($file, $content);
        return $file;
    }
}