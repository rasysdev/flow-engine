<?php

namespace Tests\Infrastructure\Analyzer;

use FlowEngine\Domain\Flow\DefaultNodeFactory;
use FlowEngine\Infrastructure\Analyzer\AstParser;
use PHPUnit\Framework\TestCase;

final class AstParserEdgeDetectionTest extends TestCase
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
        // Limpar arquivos temporários
        array_map('unlink', glob($this->tempDir . '/*.php'));
        rmdir($this->tempDir);
    }

    public function test_detects_method_call_on_this(): void
    {
        $file = $this->createTempFile('<?php
namespace App;
class Calculator {
    public function calculate() {
        return $this->sum(1, 2);
    }
    public function sum($a, $b) {
        return $a + $b;
    }
}
');

        $result = $this->parser->parse($file);

        $this->assertCount(2, $result['nodes']);
        $this->assertCount(1, $result['edges']);

        $edge = $result['edges'][0];
        $this->assertEquals('App\Calculator::calculate', $edge->from());
        $this->assertEquals('App\Calculator::sum', $edge->to());
        $this->assertEquals('sum', $edge->method());
    }

    public function test_detects_static_method_call(): void
    {
        $file = $this->createTempFile('<?php
namespace App;
class UserService {
    public function create() {
        Validator::check();
    }
}
class Validator {
    public static function check() {}
}
');

        $result = $this->parser->parse($file);

        $this->assertCount(2, $result['nodes']);
        $this->assertCount(1, $result['edges']);

        $edge = $result['edges'][0];
        $this->assertEquals('App\UserService::create', $edge->from());
        $this->assertEquals('App\Validator::check', $edge->to());
    }

    public function test_detects_static_method_call_with_use_alias(): void
    {
        $file = $this->createTempFile('<?php
namespace App;

use Vendor\Toolkit\Validator as InputValidator;

class UserService {
    public function create() {
        InputValidator::check();
    }
}
');

        $result = $this->parser->parse($file);
        $this->assertCount(1, $result['edges']);
        $this->assertEquals('Vendor\Toolkit\Validator::check', $result['edges'][0]->to());
    }

    public function test_detects_static_method_call_with_group_use_import(): void
    {
        $file = $this->createTempFile('<?php
namespace App;

use FlowEngine\AI\Prompt\{InterpretationPrompts, SystemPrompt};

class Interpreter {
    public function run(): void {
        InterpretationPrompts::cycles();
        SystemPrompt::text();
    }
}
');

        $result = $this->parser->parse($file);
        $targets = array_map(fn($e) => $e->to(), $result['edges']);

        $this->assertContains('FlowEngine\AI\Prompt\InterpretationPrompts::cycles', $targets);
        $this->assertContains('FlowEngine\AI\Prompt\SystemPrompt::text', $targets);
        $this->assertNotContains('App\InterpretationPrompts::cycles', $targets);
        $this->assertNotContains('App\SystemPrompt::text', $targets);
    }

    public function test_detects_new_instance_method_call(): void
    {
        $file = $this->createTempFile('<?php
namespace App;
class Controller {
    public function handle() {
        (new Service())->execute();
    }
}
class Service {
    public function execute() {}
}
');

        $result = $this->parser->parse($file);

        $this->assertCount(2, $result['nodes']);
        // Now includes direct_instantiation edge + method call edge
        $this->assertCount(2, $result['edges']);

        $edgeTargets = array_map(fn($e) => $e->to(), $result['edges']);
        $this->assertContains('App\Service::execute', $edgeTargets);
        $this->assertContains('App\Service::__construct', $edgeTargets);

        $callEdge = array_values(array_filter($result['edges'], fn($e) => $e->to() === 'App\Service::execute'))[0];
        $this->assertEquals('App\Controller::handle', $callEdge->from());

        $instantiationEdge = array_values(array_filter($result['edges'], fn($e) => $e->type() === 'direct_instantiation'))[0];
        $this->assertEquals('App\Controller::handle', $instantiationEdge->from());
        $this->assertEquals('App\Service::__construct', $instantiationEdge->to());
    }

    public function test_detects_new_instance_method_call_with_use_alias(): void
    {
        $file = $this->createTempFile('<?php
namespace App;

use Vendor\Toolkit\Service as ExternalService;

class Controller {
    public function handle() {
        (new ExternalService())->execute();
    }
}
');

        $result = $this->parser->parse($file);
        // Now includes direct_instantiation edge + method call edge
        $this->assertCount(2, $result['edges']);

        $edgeTargets = array_map(fn($e) => $e->to(), $result['edges']);
        $this->assertContains('Vendor\Toolkit\Service::execute', $edgeTargets);
        $this->assertContains('Vendor\Toolkit\Service::__construct', $edgeTargets);

        $callEdge = array_values(array_filter($result['edges'], fn($e) => $e->to() === 'Vendor\Toolkit\Service::execute'))[0];
        $this->assertEquals('Vendor\Toolkit\Service::execute', $callEdge->to());
    }

    public function test_detects_chained_method_calls(): void
    {
        $file = $this->createTempFile('<?php
namespace App;
class Builder {
    public function build() {
        return $this->step1()->step2();
    }
    public function step1() { return $this; }
    public function step2() { return $this; }
}
');

        $result = $this->parser->parse($file);

        $this->assertCount(3, $result['nodes']);

        // Deve detectar ambas as chamadas
        $this->assertGreaterThanOrEqual(2, count($result['edges']));

        $edgeIds = array_map(fn($e) => $e->to(), $result['edges']);
        $this->assertContains('App\Builder::step1', $edgeIds);
        $this->assertContains('App\Builder::step2', $edgeIds);
    }

    public function test_handles_file_without_method_calls(): void
    {
        $file = $this->createTempFile('<?php
namespace App;
class Simple {
    public function getValue() {
        return 42;
    }
}
');

        $result = $this->parser->parse($file);

        $this->assertCount(1, $result['nodes']);
        $this->assertCount(0, $result['edges']); // sem edges
    }

    public function test_ignores_dynamic_method_calls(): void
    {
        $file = $this->createTempFile('<?php
namespace App;
class Dynamic {
    public function call($method) {
        return $this->$method(); // método dinâmico - ignorar
    }
}
');

        $result = $this->parser->parse($file);

        $this->assertCount(1, $result['nodes']);
        $this->assertCount(0, $result['edges']); // ignorado
    }

    public function test_multiple_calls_in_same_method(): void
    {
        $file = $this->createTempFile('<?php
namespace App;
class Workflow {
    public function execute() {
        $this->stepA();
        $this->stepB();
        $this->stepC();
    }
    public function stepA() {}
    public function stepB() {}
    public function stepC() {}
}
');

        $result = $this->parser->parse($file);

        $this->assertCount(4, $result['nodes']);
        $this->assertCount(3, $result['edges']); // 3 chamadas

        foreach ($result['edges'] as $edge) {
            $this->assertEquals('App\Workflow::execute', $edge->from());
        }
    }

    public function test_detects_fluent_container_use_case_dispatch_execute_call(): void
    {
        $file = $this->createTempFile('<?php
namespace App;

use FlowEngine\Bootstrap\Container;

class BugsCommand {
    public function handle(string $projectPath): void {
        $container = new Container($projectPath);
        $container->analyzeBugs()->execute(0, "");
    }
}
');

        $result = $this->parser->parse($file);

        $edgeTargets = array_map(fn($e) => $e->to(), $result['edges']);

        $this->assertContains('FlowEngine\Bootstrap\Container::analyzeBugs', $edgeTargets);
        $this->assertContains('FlowEngine\Application\UseCase\AnalyzeBugs::execute', $edgeTargets);
    }

    private function createTempFile(string $content): string
    {
        $file = $this->tempDir . '/' . uniqid('test_', true) . '.php';
        file_put_contents($file, $content);
        return $file;
    }
}
