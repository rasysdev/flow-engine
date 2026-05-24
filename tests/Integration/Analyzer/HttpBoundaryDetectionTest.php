<?php

namespace Tests\Integration\Analyzer;

use FlowEngine\Domain\Flow\DefaultNodeFactory;
use FlowEngine\Infrastructure\Analyzer\AstParser;
use PHPUnit\Framework\TestCase;

final class HttpBoundaryDetectionTest extends TestCase
{
    public function test_laravel_http_facade_calls_produce_http_call_edges_for_external_hosts(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'flow_http_int_') . '.php';
        file_put_contents($file, '<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class BackupApiService
{
    public function deploy(string $clientId): void
    {
        Http::timeout(30)->post("http://noc_backup_manager:8000/agent/deploy", [
            "client_id" => $clientId,
        ]);
    }

    public function check(string $clientId): void
    {
        Http::get("http://noc_backup_manager:8000/agent/check/{$clientId}");
    }
}
');

        try {
            $parser = new AstParser(new DefaultNodeFactory());
            $result = $parser->parse($file);
        } finally {
            @unlink($file);
        }

        $httpEdges = array_values(array_filter(
            $result['edges'],
            fn($e) => $e->type() === 'http_call'
        ));

        $this->assertCount(2, $httpEdges, 'Expected 2 http_call edges');

        $tos = array_map(fn($e) => $e->to(), $httpEdges);
        $this->assertContains('http:POST:http://noc_backup_manager:8000/agent/deploy', $tos);
        $this->assertContains('http:GET:http://noc_backup_manager:8000/agent/check/{*}', $tos);

        foreach ($httpEdges as $edge) {
            $this->assertStringStartsWith('App\Services\BackupApiService::', $edge->from());
        }
    }
}
