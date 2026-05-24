<?php

use PHPUnit\Framework\TestCase;
use FlowEngine\AI\Context\ViolationContext;

final class ViolationContextTest extends TestCase
{
    public function test_readonly_properties(): void
    {
        $ctx = new ViolationContext(
            isClean: false,
            totalViolations: 3,
            bySeverity: ['error' => 2, 'warning' => 1],
            byType: ['layer_violation' => 3],
            layerDistribution: ['domain' => 5, 'application' => 8, 'infrastructure' => 4],
            violations: [
                ['from' => 'Domain\\A', 'to' => 'Infra\\B', 'type' => 'layer_violation'],
            ]
        );

        $this->assertFalse($ctx->isClean);
        $this->assertSame(3, $ctx->totalViolations);
        $this->assertSame(['error' => 2, 'warning' => 1], $ctx->bySeverity);
        $this->assertSame(['layer_violation' => 3], $ctx->byType);
        $this->assertCount(3, $ctx->layerDistribution);
        $this->assertCount(1, $ctx->violations);
    }

    public function test_is_json_serializable(): void
    {
        $ctx = new ViolationContext(
            isClean: true,
            totalViolations: 0,
            bySeverity: [],
            byType: [],
            layerDistribution: [],
            violations: []
        );

        $json = json_encode($ctx);
        $this->assertIsString($json);
        $this->assertJson($json);
    }
}
