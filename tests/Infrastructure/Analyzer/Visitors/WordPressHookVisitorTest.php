<?php

namespace Tests\Infrastructure\Analyzer\Visitors;

use FlowEngine\Domain\Flow\DefaultNodeFactory;
use FlowEngine\Infrastructure\Analyzer\AstParser;
use PHPUnit\Framework\TestCase;

final class WordPressHookVisitorTest extends TestCase
{
    private function parse(string $code): array
    {
        $file = tempnam(sys_get_temp_dir(), 'flow_wp_') . '.php';
        file_put_contents($file, $code);

        try {
            $parser = new AstParser(new DefaultNodeFactory());
            return $parser->parse($file);
        } finally {
            @unlink($file);
        }
    }

    public function test_add_action_with_this_creates_wp_hook_edge(): void
    {
        $result = $this->parse('<?php
namespace App\Plugin;

class MyPlugin {
    public function register() {
        add_action("init", [$this, "onInit"]);
    }
    public function onInit() {}
}
');
        $edgeTypes = array_map(fn($e) => $e->type(), $result['edges']);
        $this->assertContains('wp_hook', $edgeTypes);

        $wpEdges = array_filter($result['edges'], fn($e) => $e->type() === 'wp_hook');
        $wpEdge = array_values($wpEdges)[0];

        $this->assertSame('App\Plugin\MyPlugin::register', $wpEdge->from());
        $this->assertSame('App\Plugin\MyPlugin::onInit', $wpEdge->to());
    }

    public function test_add_filter_with_this_creates_wp_hook_edge(): void
    {
        $result = $this->parse('<?php
namespace App\Plugin;

class MyPlugin {
    public function boot() {
        add_filter("the_content", [$this, "filterContent"]);
    }
    public function filterContent(string $content): string { return $content; }
}
');
        $wpEdges = array_filter($result['edges'], fn($e) => $e->type() === 'wp_hook');
        $this->assertCount(1, $wpEdges);

        $wpEdge = array_values($wpEdges)[0];
        $this->assertSame('App\Plugin\MyPlugin::filterContent', $wpEdge->to());
    }

    public function test_add_action_with_class_const_creates_edge(): void
    {
        $result = $this->parse('<?php
namespace App\Plugin;

class Registrar {
    public function register() {
        add_action("init", [Handler::class, "handle"]);
    }
}
class Handler {
    public function handle() {}
}
');
        $wpEdges = array_filter($result['edges'], fn($e) => $e->type() === 'wp_hook');
        $this->assertNotEmpty($wpEdges);

        $wpEdge = array_values($wpEdges)[0];
        $this->assertSame('App\Plugin\Handler::handle', $wpEdge->to());
    }

    public function test_add_action_with_string_class_method_creates_edge(): void
    {
        $result = $this->parse('<?php
namespace App\Plugin;

class Registrar {
    public function register() {
        add_action("init", "App\Plugin\Handler::handle");
    }
}
');
        $wpEdges = array_filter($result['edges'], fn($e) => $e->type() === 'wp_hook');
        $this->assertNotEmpty($wpEdges);

        $wpEdge = array_values($wpEdges)[0];
        $this->assertStringContainsString('Handler::handle', $wpEdge->to());
    }

    public function test_no_wp_hook_edge_for_regular_function_calls(): void
    {
        $result = $this->parse('<?php
namespace App;

class Service {
    public function doSomething() {
        some_function("arg");
        other_function([$this, "callback"]);
    }
    public function callback() {}
}
');
        $wpEdges = array_filter($result['edges'], fn($e) => $e->type() === 'wp_hook');
        $this->assertEmpty($wpEdges);
    }
}
