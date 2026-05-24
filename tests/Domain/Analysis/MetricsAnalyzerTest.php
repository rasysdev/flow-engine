<?php

namespace Tests\Domain\Analysis;

use FlowEngine\Domain\Analysis\MetricsAnalyzer;
use FlowEngine\Domain\Flow\Edge;
use FlowEngine\Domain\Flow\Flow;
use FlowEngine\Domain\Flow\Node;
use PHPUnit\Framework\TestCase;

final class MetricsAnalyzerTest extends TestCase
{
    public function test_calculates_fan_in(): void
    {
        // A → B, C → B (B tem fan-in = 2)
        $nodes = [
            new Node('A', 'method', __FILE__, 1),
            new Node('B', 'method', __FILE__, 2),
            new Node('C', 'method', __FILE__, 3),
        ];

        $edges = [
            new Edge('A::method', 'B::method', 'method'),
            new Edge('C::method', 'B::method', 'method'),
        ];

        $flow = new Flow($nodes, $edges);
        $analyzer = new MetricsAnalyzer($flow);

        $metrics = $analyzer->metricsFor('B::method');

        $this->assertEquals(2, $metrics->fanIn);
    }

    public function test_calculates_fan_out(): void
    {
        // A → B, A → C (A tem fan-out = 2)
        $nodes = [
            new Node('A', 'method', __FILE__, 1),
            new Node('B', 'method', __FILE__, 2),
            new Node('C', 'method', __FILE__, 3),
        ];

        $edges = [
            new Edge('A::method', 'B::method', 'method'),
            new Edge('A::method', 'C::method', 'method'),
        ];

        $flow = new Flow($nodes, $edges);
        $analyzer = new MetricsAnalyzer($flow);

        $metrics = $analyzer->metricsFor('A::method');

        $this->assertEquals(2, $metrics->fanOut);
    }

    public function test_identifies_hotspots(): void
    {
        // Criar um node com fan-in alto (hotspot)
        $nodes = [
            new Node('HotNode', 'method', __FILE__, 1),
        ];

        // Adicionar mais 11 nodes que chamam HotNode
        for ($i = 1; $i <= 11; $i++) {
            $nodes[] = new Node("Caller{$i}", 'method', __FILE__, $i + 1);
        }

        $edges = [];
        for ($i = 1; $i <= 11; $i++) {
            $edges[] = new Edge("Caller{$i}::method", 'HotNode::method', 'method');
        }

        $flow = new Flow($nodes, $edges);
        $analyzer = new MetricsAnalyzer($flow);

        $hotspots = $analyzer->hotspots();

        $this->assertCount(1, $hotspots);
        $this->assertEquals('HotNode::method', $hotspots[0]->nodeId);
        $this->assertEquals('HIGH', $hotspots[0]->riskLevel);
    }

    public function test_calculates_blast_radius(): void
    {
        // A → B → C (se C mudar, afeta B e A)
        $nodes = [
            new Node('A', 'method', __FILE__, 1),
            new Node('B', 'method', __FILE__, 2),
            new Node('C', 'method', __FILE__, 3),
        ];

        $edges = [
            new Edge('A::method', 'B::method', 'method'),
            new Edge('B::method', 'C::method', 'method'),
        ];

        $flow = new Flow($nodes, $edges);
        $analyzer = new MetricsAnalyzer($flow);

        $metricsC = $analyzer->metricsFor('C::method');

        // C afeta B e A (blast radius = 2)
        $this->assertEquals(2, $metricsC->blastRadius);
    }

    public function test_calculates_complexity_score(): void
    {
        $nodes = [
            new Node('Complex', 'method', __FILE__, 1),
            new Node('B', 'method', __FILE__, 2),
        ];

        // Complex tem fan-in = 1, fan-out = 1
        $edges = [
            new Edge('B::method', 'Complex::method', 'method'),
            new Edge('Complex::method', 'B::method', 'method'),
        ];

        $flow = new Flow($nodes, $edges);
        $analyzer = new MetricsAnalyzer($flow);

        $metrics = $analyzer->metricsFor('Complex::method');

        // Score = (fan-in * 2) + (fan-out * 3) + (blast-radius * 0.5)
        // Score = (1 * 2) + (1 * 3) + (1 * 0.5) = 5.5 = 5
        $this->assertGreaterThan(0, $metrics->complexityScore());
    }

    public function test_returns_top_coupled_methods(): void
    {
        $nodes = [];
        $edges = [];

        // Criar nodes com diferentes níveis de acoplamento
        for ($i = 1; $i <= 5; $i++) {
            $nodes[] = new Node("Node{$i}", 'method', __FILE__, $i);
        }

        // Node1 chama todos os outros (fan-out = 4)
        for ($i = 2; $i <= 5; $i++) {
            $edges[] = new Edge('Node1::method', "Node{$i}::method", 'method');
        }

        $flow = new Flow($nodes, $edges);
        $analyzer = new MetricsAnalyzer($flow);

        $topCoupled = $analyzer->topCoupled(3);

        $this->assertCount(3, $topCoupled);
        $this->assertEquals('Node1::method', $topCoupled[0]->nodeId);
    }

    public function test_calculates_stats(): void
    {
        $nodes = [
            new Node('A', 'method', __FILE__, 1),
            new Node('B', 'method', __FILE__, 2),
        ];

        $edges = [
            new Edge('A::method', 'B::method', 'method'),
        ];

        $flow = new Flow($nodes, $edges);
        $analyzer = new MetricsAnalyzer($flow);

        $stats = $analyzer->stats();

        $this->assertEquals(2, $stats['totalNodes']);
        $this->assertEquals(1, $stats['totalEdges']);
        $this->assertArrayHasKey('avgFanIn', $stats);
        $this->assertArrayHasKey('avgFanOut', $stats);
        $this->assertArrayHasKey('hotspotCount', $stats);
    }

    public function test_handles_isolated_nodes(): void
    {
        // Node sem edges
        $nodes = [new Node('Isolated', 'method', __FILE__, 1)];
        $edges = [];

        $flow = new Flow($nodes, $edges);
        $analyzer = new MetricsAnalyzer($flow);

        $metrics = $analyzer->metricsFor('Isolated::method');

        $this->assertEquals(0, $metrics->fanIn);
        $this->assertEquals(0, $metrics->fanOut);
        $this->assertEquals(0, $metrics->blastRadius);
        $this->assertEquals('LOW', $metrics->riskLevel);
    }
}