<?php

namespace Tests\Infrastructure\Analyzer;

use FlowEngine\Domain\Flow\DefaultNodeFactory;
use FlowEngine\Infrastructure\Analyzer\AstParser;
use PHPUnit\Framework\TestCase;

final class AstParserTraitInterfaceTest extends TestCase
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

    public function test_detects_trait_usage(): void
    {
        $file = $this->createTempFile('<?php
namespace App;

trait Loggable {
    public function log() {}
}

class Service {
    use Loggable;
    
    public function execute() {
        $this->log();
    }
}
');

        $result = $this->parser->parse($file);

        // Deve criar nodes pra Service::execute E Loggable::log (trait methods também viram nodes desde v4.35.0)
        $this->assertCount(2, $result['nodes']);

        // Deve detectar: trait usage + method call
        $edges = $result['edges'];
        $this->assertGreaterThan(0, count($edges));

        // Verificar trait usage edge
        $traitEdges = array_filter($edges, fn($e) => $e->type() === 'trait_usage');
        $this->assertCount(1, $traitEdges);

        $traitEdge = reset($traitEdges);
        $this->assertEquals('App\Service::__construct', $traitEdge->from());
        $this->assertEquals('App\Loggable::__trait', $traitEdge->to());
        $this->assertEquals('use', $traitEdge->method());
    }

    public function test_detects_multiple_traits(): void
    {
        $file = $this->createTempFile('<?php
namespace App;

trait TraitA {}
trait TraitB {}
trait TraitC {}

class Service {
    use TraitA, TraitB, TraitC;
}
');

        $result = $this->parser->parse($file);

        $traitEdges = array_filter($result['edges'], fn($e) => $e->type() === 'trait_usage');

        // Deve detectar 3 traits
        $this->assertCount(3, $traitEdges);

        $traitNames = array_map(fn($e) => $e->to(), $traitEdges);
        $this->assertContains('App\TraitA::__trait', $traitNames);
        $this->assertContains('App\TraitB::__trait', $traitNames);
        $this->assertContains('App\TraitC::__trait', $traitNames);
    }

    public function test_detects_interface_implementation(): void
    {
        $file = $this->createTempFile('<?php
namespace App;

interface Runnable {
    public function run();
}

class Task implements Runnable {
    public function run() {}
}
');

        $result = $this->parser->parse($file);

        $interfaceEdges = array_filter($result['edges'], fn($e) => $e->type() === 'interface_implementation');

        $this->assertCount(1, $interfaceEdges);

        $edge = reset($interfaceEdges);
        $this->assertEquals('App\Task::__construct', $edge->from());
        $this->assertEquals('App\Runnable::__interface', $edge->to());
        $this->assertEquals('implements', $edge->method());
    }

    public function test_detects_multiple_interfaces(): void
    {
        $file = $this->createTempFile('<?php
namespace App;

interface InterfaceA {}
interface InterfaceB {}

class Service implements InterfaceA, InterfaceB {
    public function execute() {}
}
');

        $result = $this->parser->parse($file);

        $interfaceEdges = array_filter($result['edges'], fn($e) => $e->type() === 'interface_implementation');

        $this->assertCount(2, $interfaceEdges);

        $interfaceNames = array_map(fn($e) => $e->to(), $interfaceEdges);
        $this->assertContains('App\InterfaceA::__interface', $interfaceNames);
        $this->assertContains('App\InterfaceB::__interface', $interfaceNames);
    }

    public function test_combined_traits_and_interfaces(): void
    {
        $file = $this->createTempFile('<?php
namespace App;

trait Loggable {}
interface Runnable {}

class Service implements Runnable {
    use Loggable;
    
    public function run() {}
}
');

        $result = $this->parser->parse($file);

        $edges = $result['edges'];

        $traitEdges = array_filter($edges, fn($e) => $e->type() === 'trait_usage');
        $interfaceEdges = array_filter($edges, fn($e) => $e->type() === 'interface_implementation');

        $this->assertCount(1, $traitEdges);
        $this->assertCount(1, $interfaceEdges);
    }

    public function test_resolves_fqn_for_traits(): void
    {
        $file = $this->createTempFile('<?php
namespace App\\Services;

trait Helper {}

class MyService {
    use Helper; // Deve resolver pra App\\Services\\Helper
}
');

        $result = $this->parser->parse($file);

        $traitEdges = array_filter($result['edges'], fn($e) => $e->type() === 'trait_usage');
        $edge = reset($traitEdges);

        $this->assertEquals('App\Services\Helper::__trait', $edge->to());
    }

    public function test_class_without_traits_or_interfaces(): void
    {
        $file = $this->createTempFile('<?php
namespace App;

class Simple {
    public function method() {}
}
');

        $result = $this->parser->parse($file);

        $traitEdges = array_filter($result['edges'], fn($e) => $e->type() === 'trait_usage');
        $interfaceEdges = array_filter($result['edges'], fn($e) => $e->type() === 'interface_implementation');

        $this->assertCount(0, $traitEdges);
        $this->assertCount(0, $interfaceEdges);
    }

    public function test_edge_type_helpers(): void
    {
        $file = $this->createTempFile('<?php
namespace App;

trait MyTrait {}
interface MyInterface {}

class Service implements MyInterface {
    use MyTrait;
}
');

        $result = $this->parser->parse($file);

        foreach ($result['edges'] as $edge) {
            if ($edge->type() === 'trait_usage') {
                $this->assertTrue($edge->isTraitUsage());
                $this->assertFalse($edge->isInterfaceImplementation());
            }

            if ($edge->type() === 'interface_implementation') {
                $this->assertTrue($edge->isInterfaceImplementation());
                $this->assertFalse($edge->isTraitUsage());
            }
        }

        $this->assertTrue(true); // Se chegou aqui, helpers funcionam
    }

    public function test_trait_methods_produce_nodes_marked_as_trait(): void
    {
        $file = $this->createTempFile('<?php
namespace App;

trait LoadsClients {
    public function loadClients(): void {}
}
');
        $result = $this->parser->parse($file);

        $this->assertCount(1, $result['nodes']);
        $node = $result['nodes'][0];
        $this->assertSame('App\LoadsClients::loadClients', $node->id());
        $this->assertTrue($node->metadata()['isTrait'] ?? false);
    }

    public function test_http_call_inside_trait_method_emits_http_call_edge(): void
    {
        $file = $this->createTempFile('<?php
namespace App\Http\Livewire\Concerns;
use Illuminate\Support\Facades\Http;

trait LoadsClients {
    protected function loadClients(): void {
        $response = Http::timeout(30)->get("http://api.example.com/clients");
    }
}
');
        $result = $this->parser->parse($file);

        $httpEdges = array_values(array_filter(
            $result['edges'],
            fn($e) => $e->type() === 'http_call'
        ));

        $this->assertCount(1, $httpEdges);
        $this->assertSame('App\Http\Livewire\Concerns\LoadsClients::loadClients', $httpEdges[0]->from());
        $this->assertSame('http:GET:http://api.example.com/clients', $httpEdges[0]->to());
    }

    public function test_trait_that_uses_another_trait_emits_nested_trait_usage(): void
    {
        $file = $this->createTempFile('<?php
namespace App;

trait Base {
    public function baseMethod(): void {}
}

trait Extended {
    use Base;
    public function extendedMethod(): void {}
}
');
        $result = $this->parser->parse($file);

        $ids = array_map(fn($n) => $n->id(), $result['nodes']);
        $this->assertContains('App\Base::baseMethod', $ids);
        $this->assertContains('App\Extended::extendedMethod', $ids);

        $traitEdges = array_values(array_filter(
            $result['edges'],
            fn($e) => $e->type() === 'trait_usage'
        ));
        $this->assertCount(1, $traitEdges);
        $this->assertSame('App\Extended::__construct', $traitEdges[0]->from());
        $this->assertSame('App\Base::__trait', $traitEdges[0]->to());
    }

    private function createTempFile(string $content): string
    {
        $file = $this->tempDir . '/' . uniqid('test_', true) . '.php';
        file_put_contents($file, $content);
        return $file;
    }
}
