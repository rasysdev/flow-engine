<?php

use PHPUnit\Framework\TestCase;
use FlowEngine\AI\Context\CycleContext;

final class CycleContextTest extends TestCase
{
    public function test_readonly_properties(): void
    {
        $ctx = new CycleContext(
            totalCycles: 2,
            totalNodesInCycles: 5,
            bySeverity: ['high' => 1, 'medium' => 1],
            largestCycle: 3,
            cycles: [
                ['nodes' => ['A::a', 'B::b', 'A::a'], 'severity' => 'high'],
            ]
        );

        $this->assertSame(2, $ctx->totalCycles);
        $this->assertSame(5, $ctx->totalNodesInCycles);
        $this->assertSame(['high' => 1, 'medium' => 1], $ctx->bySeverity);
        $this->assertSame(3, $ctx->largestCycle);
        $this->assertCount(1, $ctx->cycles);
    }

    public function test_is_json_serializable(): void
    {
        $ctx = new CycleContext(
            totalCycles: 0,
            totalNodesInCycles: 0,
            bySeverity: [],
            largestCycle: 0,
            cycles: []
        );

        $json = json_encode($ctx);
        $this->assertIsString($json);
        $this->assertJson($json);
    }
}
