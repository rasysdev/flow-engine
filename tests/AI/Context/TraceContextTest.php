<?php

use PHPUnit\Framework\TestCase;
use FlowEngine\AI\Context\TraceContext;

final class TraceContextTest extends TestCase
{
    public function test_readonly_properties(): void
    {
        $ctx = new TraceContext(
            nodeId: 'App\\Service::handle',
            upstream: ['App\\Controller::index'],
            downstream: ['App\\Repository::find']
        );

        $this->assertSame('App\\Service::handle', $ctx->nodeId);
        $this->assertSame(['App\\Controller::index'], $ctx->upstream);
        $this->assertSame(['App\\Repository::find'], $ctx->downstream);
    }

    public function test_is_json_serializable(): void
    {
        $ctx = new TraceContext(
            nodeId: 'A::b',
            upstream: [],
            downstream: []
        );

        $json = json_encode($ctx);
        $this->assertIsString($json);
        $this->assertJson($json);
    }
}
