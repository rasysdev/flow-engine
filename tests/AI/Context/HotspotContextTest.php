<?php

use PHPUnit\Framework\TestCase;
use FlowEngine\AI\Context\HotspotContext;

final class HotspotContextTest extends TestCase
{
    public function test_readonly_properties(): void
    {
        $ctx = new HotspotContext(
            totalMethods: 10,
            avgComplexity: 3.5,
            maxComplexity: 12,
            byLevel: ['low' => 8, 'medium' => 1, 'high' => 1],
            complexMethods: [
                ['nodeId' => 'A::handle', 'complexity' => 12, 'level' => 'high'],
            ]
        );

        $this->assertSame(10, $ctx->totalMethods);
        $this->assertSame(3.5, $ctx->avgComplexity);
        $this->assertSame(12, $ctx->maxComplexity);
        $this->assertCount(3, $ctx->byLevel);
        $this->assertCount(1, $ctx->complexMethods);
    }

    public function test_is_json_serializable(): void
    {
        $ctx = new HotspotContext(
            totalMethods: 0,
            avgComplexity: 0.0,
            maxComplexity: 0,
            byLevel: [],
            complexMethods: []
        );

        $json = json_encode($ctx);
        $this->assertIsString($json);
        $this->assertJson($json);
    }
}
