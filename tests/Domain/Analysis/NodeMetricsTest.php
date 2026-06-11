<?php

namespace Tests\Domain\Analysis;

use FlowEngine\Domain\Analysis\NodeMetrics;
use PHPUnit\Framework\TestCase;

final class NodeMetricsTest extends TestCase
{
    public function test_cap_fires_when_fan_in_zero_and_blast_radius_zero(): void
    {
        // fanIn 0, fanOut 53, blastRadius 0 -> no callers, no downstream -> HIGH (not CRITICAL)
        $level = NodeMetrics::calculateRiskLevel(0, 53, 0);

        $this->assertEquals('HIGH', $level);
    }

    public function test_cap_does_not_fire_when_blast_radius_positive_even_with_zero_fan_in(): void
    {
        // fanIn 0 but blastRadius 10 -> something downstream is affected -> stays CRITICAL.
        // Proves the cap requires BOTH fanIn and blastRadius to be zero.
        $level = NodeMetrics::calculateRiskLevel(0, 53, 10);

        $this->assertEquals('CRITICAL', $level);
    }

    public function test_cap_not_applied_when_blast_radius_unknown(): void
    {
        // No blastRadius passed -> default null means "unknown", so the cap must NOT
        // fire; high fan-out keeps it CRITICAL.
        $level = NodeMetrics::calculateRiskLevel(0, 53);

        $this->assertEquals('CRITICAL', $level);
    }

    public function test_low_risk_for_simple_node(): void
    {
        $level = NodeMetrics::calculateRiskLevel(1, 3);

        $this->assertEquals('LOW', $level);
    }
}
