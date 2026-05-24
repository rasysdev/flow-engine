<?php

use PHPUnit\Framework\TestCase;
use FlowEngine\Domain\Analysis\RiskScorer;
use FlowEngine\Domain\Analysis\RiskScore;
use FlowEngine\Domain\Analysis\NodeMetrics;

final class RiskScorerTest extends TestCase
{
    private RiskScorer $scorer;

    protected function setUp(): void
    {
        $this->scorer = new RiskScorer();
    }

    public function test_returns_risk_score_value_object(): void
    {
        $metrics = new NodeMetrics('App\\Service::handle', 2, 3, 1, 'LOW');
        $result = $this->scorer->score($metrics, 0, 0, 5);

        $this->assertInstanceOf(RiskScore::class, $result);
    }

    public function test_low_risk_for_simple_node(): void
    {
        $metrics = new NodeMetrics('App\\Service::handle', 1, 1, 0, 'LOW');
        $result = $this->scorer->score($metrics, 0, 0, 1);

        $this->assertSame('LOW', $result->level);
        $this->assertLessThanOrEqual(25, $result->score);
    }

    public function test_critical_risk_for_highly_coupled_node(): void
    {
        $metrics = new NodeMetrics('App\\Core::process', 25, 20, 50, 'CRITICAL');
        $result = $this->scorer->score($metrics, 3, 2, 40);

        $this->assertSame('CRITICAL', $result->level);
        $this->assertGreaterThan(75, $result->score);
    }

    public function test_medium_risk_for_moderate_node(): void
    {
        $metrics = new NodeMetrics('App\\Handler::exec', 5, 4, 8, 'MEDIUM');
        $result = $this->scorer->score($metrics, 1, 0, 10);

        $this->assertContains($result->level, ['MEDIUM', 'HIGH']);
        $this->assertGreaterThan(0, $result->score);
    }

    public function test_factors_are_tracked(): void
    {
        $metrics = new NodeMetrics('App\\Service::handle', 3, 2, 5, 'LOW');
        $result = $this->scorer->score($metrics, 1, 0, 8);

        $this->assertNotEmpty($result->factors);
        $this->assertCount(6, $result->factors);

        $factorNames = array_column($result->factors, 'name');
        $this->assertContains('blastRadius', $factorNames);
        $this->assertContains('fanIn', $factorNames);
        $this->assertContains('fanOut', $factorNames);
        $this->assertContains('cycleCount', $factorNames);
        $this->assertContains('violationCount', $factorNames);
        $this->assertContains('cyclomaticComplexity', $factorNames);
    }

    public function test_each_factor_has_required_keys(): void
    {
        $metrics = new NodeMetrics('App\\Service::handle', 2, 3, 4, 'LOW');
        $result = $this->scorer->score($metrics, 0, 0, 5);

        foreach ($result->factors as $factor) {
            $this->assertArrayHasKey('name', $factor);
            $this->assertArrayHasKey('weight', $factor);
            $this->assertArrayHasKey('value', $factor);
            $this->assertArrayHasKey('contribution', $factor);
        }
    }

    public function test_score_is_normalized_to_0_100(): void
    {
        $metrics = new NodeMetrics('App\\Monster::handle', 100, 100, 200, 'CRITICAL');
        $result = $this->scorer->score($metrics, 50, 50, 200);

        $this->assertLessThanOrEqual(100, $result->score);
        $this->assertGreaterThanOrEqual(0, $result->score);
    }

    public function test_zero_everything_returns_low(): void
    {
        $metrics = new NodeMetrics('App\\Empty::noop', 0, 0, 0, 'LOW');
        $result = $this->scorer->score($metrics, 0, 0, 0);

        $this->assertSame(0, $result->score);
        $this->assertSame('LOW', $result->level);
    }

    public function test_to_array_serialization(): void
    {
        $metrics = new NodeMetrics('App\\Service::handle', 3, 2, 5, 'LOW');
        $result = $this->scorer->score($metrics, 1, 0, 8);

        $array = $result->toArray();

        $this->assertArrayHasKey('score', $array);
        $this->assertArrayHasKey('level', $array);
        $this->assertArrayHasKey('factors', $array);
        $this->assertSame($result->score, $array['score']);
        $this->assertSame($result->level, $array['level']);
    }

    public function test_cycles_heavily_weighted(): void
    {
        $metricsNoCycles = new NodeMetrics('App\\A::handle', 2, 2, 3, 'LOW');
        $metricsWithCycles = new NodeMetrics('App\\A::handle', 2, 2, 3, 'LOW');

        $noCycles = $this->scorer->score($metricsNoCycles, 0, 0, 5);
        $withCycles = $this->scorer->score($metricsWithCycles, 3, 0, 5);

        $this->assertGreaterThan($noCycles->score, $withCycles->score);
    }
}
