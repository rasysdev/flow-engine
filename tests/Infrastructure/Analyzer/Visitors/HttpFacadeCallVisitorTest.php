<?php

namespace Tests\Infrastructure\Analyzer\Visitors;

use FlowEngine\Domain\Flow\DefaultNodeFactory;
use FlowEngine\Infrastructure\Analyzer\AstParser;
use PHPUnit\Framework\TestCase;

final class HttpFacadeCallVisitorTest extends TestCase
{
    private function parse(string $code): array
    {
        $file = tempnam(sys_get_temp_dir(), 'flow_http_') . '.php';
        file_put_contents($file, $code);

        try {
            $parser = new AstParser(new DefaultNodeFactory());
            return $parser->parse($file);
        } finally {
            @unlink($file);
        }
    }

    private function httpEdges(array $result): array
    {
        return array_values(array_filter(
            $result['edges'],
            fn($e) => $e->type() === 'http_call'
        ));
    }

    public function test_static_call_get_with_literal_url(): void
    {
        $result = $this->parse('<?php
namespace App;
use Illuminate\Support\Facades\Http;

class Client {
    public function fetch(): void {
        Http::get("https://api.example.com/users");
    }
}
');
        $edges = $this->httpEdges($result);
        $this->assertCount(1, $edges);
        $this->assertSame('App\Client::fetch', $edges[0]->from());
        $this->assertSame('http:GET:https://api.example.com/users', $edges[0]->to());
    }

    public function test_chain_timeout_post_with_literal_url(): void
    {
        $result = $this->parse('<?php
namespace App;
use Illuminate\Support\Facades\Http;

class Client {
    public function send(): void {
        Http::timeout(30)->post("/api/users", ["foo" => "bar"]);
    }
}
');
        $edges = $this->httpEdges($result);
        $this->assertNotEmpty($edges);
        $terminal = array_values(array_filter($edges, fn($e) => str_starts_with($e->to(), 'http:POST:')));
        $this->assertCount(1, $terminal);
        $this->assertSame('http:POST:/api/users', $terminal[0]->to());
    }

    public function test_chain_with_headers_and_options(): void
    {
        $result = $this->parse('<?php
namespace App;
use Illuminate\Support\Facades\Http;

class Client {
    public function go(): void {
        Http::withHeaders(["X-Foo" => "bar"])->withOptions(["verify" => false])->post("/x");
    }
}
');
        $edges = $this->httpEdges($result);
        $posts = array_values(array_filter($edges, fn($e) => str_starts_with($e->to(), 'http:POST:')));
        $this->assertCount(1, $posts);
        $this->assertSame('http:POST:/x', $posts[0]->to());
    }

    public function test_interpolated_url_produces_placeholder(): void
    {
        $result = $this->parse('<?php
namespace App;
use Illuminate\Support\Facades\Http;

class Client {
    public function go(int $id): void {
        Http::post("http://x/{$id}/y");
    }
}
');
        $edges = $this->httpEdges($result);
        $this->assertCount(1, $edges);
        $this->assertSame('http:POST:http://x/{*}/y', $edges[0]->to());
    }

    public function test_concatenated_url_produces_placeholder(): void
    {
        $result = $this->parse('<?php
namespace App;
use Illuminate\Support\Facades\Http;

class Client {
    public function go(string $id): void {
        Http::post("http://x/" . $id);
    }
}
');
        $edges = $this->httpEdges($result);
        $this->assertCount(1, $edges);
        $this->assertSame('http:POST:http://x/{*}', $edges[0]->to());
    }

    public function test_fully_dynamic_url(): void
    {
        $result = $this->parse('<?php
namespace App;
use Illuminate\Support\Facades\Http;

class Client {
    public function go(string $url): void {
        Http::post($url);
    }
}
');
        $edges = $this->httpEdges($result);
        $this->assertCount(1, $edges);
        $this->assertSame('http:POST:<dynamic>', $edges[0]->to());
    }

    public function test_send_with_explicit_method(): void
    {
        $result = $this->parse('<?php
namespace App;
use Illuminate\Support\Facades\Http;

class Client {
    public function go(): void {
        Http::send("DELETE", "/resource/1");
    }
}
');
        $edges = $this->httpEdges($result);
        $this->assertCount(1, $edges);
        $this->assertSame('http:DELETE:/resource/1', $edges[0]->to());
    }

    public function test_config_only_chain_without_terminal_does_not_emit(): void
    {
        $result = $this->parse('<?php
namespace App;
use Illuminate\Support\Facades\Http;

class Client {
    public function go(): void {
        $pending = Http::retry(3, 100)->withHeaders([]);
    }
}
');
        $edges = $this->httpEdges($result);
        $this->assertEmpty($edges);
    }

    public function test_local_class_named_http_is_not_detected(): void
    {
        $result = $this->parse('<?php
namespace App;

class Http {
    public static function get(string $url): void {}
}

class Client {
    public function go(): void {
        Http::get("/x");
    }
}
');
        $edges = $this->httpEdges($result);
        $this->assertEmpty($edges);
    }

    public function test_facade_imported_with_alias(): void
    {
        $result = $this->parse('<?php
namespace App;
use Illuminate\Support\Facades\Http as WebClient;

class Client {
    public function go(): void {
        WebClient::get("/x");
    }
}
');
        $edges = $this->httpEdges($result);
        $this->assertCount(1, $edges);
        $this->assertSame('http:GET:/x', $edges[0]->to());
    }

    public function test_global_namespace_http_without_use(): void
    {
        $result = $this->parse('<?php
namespace App;

class Client {
    public function go(): void {
        \Illuminate\Support\Facades\Http::get("/x");
    }
}
');
        $edges = $this->httpEdges($result);
        $this->assertCount(1, $edges);
        $this->assertSame('http:GET:/x', $edges[0]->to());
    }

    public function test_multiple_calls_emit_multiple_edges(): void
    {
        $result = $this->parse('<?php
namespace App;
use Illuminate\Support\Facades\Http;

class Client {
    public function go(): void {
        Http::get("/a");
        Http::post("/b");
        Http::timeout(5)->put("/c");
    }
}
');
        $edges = $this->httpEdges($result);
        $tos = array_map(fn($e) => $e->to(), $edges);
        $this->assertContains('http:GET:/a', $tos);
        $this->assertContains('http:POST:/b', $tos);
        $this->assertContains('http:PUT:/c', $tos);
    }

    public function test_base_url_is_prepended_to_relative_path(): void
    {
        $result = $this->parse('<?php
namespace App;
use Illuminate\Support\Facades\Http;

class Client {
    public function go(): void {
        Http::baseUrl("https://api.example.com")->get("/users");
    }
}
');
        $edges = $this->httpEdges($result);
        $this->assertCount(1, $edges);
        $this->assertSame('http:GET:https://api.example.com/users', $edges[0]->to());
    }

    public function test_base_url_in_chain_with_other_configs(): void
    {
        $result = $this->parse('<?php
namespace App;
use Illuminate\Support\Facades\Http;

class Client {
    public function go(): void {
        Http::timeout(5)->baseUrl("https://api.example.com")->withHeaders([])->post("/users");
    }
}
');
        $edges = $this->httpEdges($result);
        $posts = array_values(array_filter($edges, fn($e) => str_starts_with($e->to(), 'http:POST:')));
        $this->assertCount(1, $posts);
        $this->assertSame('http:POST:https://api.example.com/users', $posts[0]->to());
    }

    public function test_absolute_url_not_prefixed_when_base_url_present(): void
    {
        $result = $this->parse('<?php
namespace App;
use Illuminate\Support\Facades\Http;

class Client {
    public function go(): void {
        Http::baseUrl("https://api.example.com")->get("https://other.com/x");
    }
}
');
        $edges = $this->httpEdges($result);
        $this->assertCount(1, $edges);
        $this->assertSame('http:GET:https://other.com/x', $edges[0]->to());
    }

    public function test_pending_request_assigned_to_variable_is_detected(): void
    {
        $result = $this->parse('<?php
namespace App;
use Illuminate\Support\Facades\Http;

class Client {
    public function go(): void {
        $client = Http::timeout(5);
        $client->post("https://api.example.com/users");
    }
}
');
        $edges = $this->httpEdges($result);
        $posts = array_values(array_filter($edges, fn($e) => str_starts_with($e->to(), 'http:POST:')));
        $this->assertCount(1, $posts);
        $this->assertSame('http:POST:https://api.example.com/users', $posts[0]->to());
    }

    public function test_pending_request_variable_from_direct_static_call(): void
    {
        $result = $this->parse('<?php
namespace App;
use Illuminate\Support\Facades\Http;

class Client {
    public function go(): void {
        $http = Http::asJson();
        $http->get("/x");
    }
}
');
        $edges = $this->httpEdges($result);
        $gets = array_values(array_filter($edges, fn($e) => str_starts_with($e->to(), 'http:GET:')));
        $this->assertCount(1, $gets);
        $this->assertSame('http:GET:/x', $gets[0]->to());
    }

    public function test_non_http_variable_is_not_detected(): void
    {
        $result = $this->parse('<?php
namespace App;

class Client {
    public function go(): void {
        $service = new \App\OtherService();
        $service->post("/x");
    }
}
');
        $edges = $this->httpEdges($result);
        $this->assertEmpty($edges);
    }
}
