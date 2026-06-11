<?php

namespace Tests\Domain\Analysis;

use FlowEngine\Domain\Analysis\CycleDetector;
use FlowEngine\Domain\Flow\Edge;
use FlowEngine\Domain\Flow\Flow;
use FlowEngine\Domain\Flow\Node;
use PHPUnit\Framework\TestCase;

final class CycleDetectorTest extends TestCase
{
    public function test_detects_no_cycles_in_acyclic_graph(): void
    {
        // A → B → C (sem ciclo)
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
        $detector = new CycleDetector($flow);

        $cycles = $detector->detectCycles();

        $this->assertEmpty($cycles);
    }

    public function test_detects_simple_cycle(): void
    {
        // A → B → A (ciclo simples)
        $nodes = [
            new Node('A', 'method', __FILE__, 1),
            new Node('B', 'method', __FILE__, 2),
        ];

        $edges = [
            new Edge('A::method', 'B::method', 'method'),
            new Edge('B::method', 'A::method', 'method'),
        ];

        $flow = new Flow($nodes, $edges);
        $detector = new CycleDetector($flow);

        $cycles = $detector->detectCycles();

        $this->assertCount(1, $cycles);
        $this->assertEquals(2, $cycles[0]['size']);
        $this->assertEquals('LOW', $cycles[0]['severity']);
    }

    public function test_detects_three_node_cycle(): void
    {
        // A → B → C → A
        $nodes = [
            new Node('A', 'method', __FILE__, 1),
            new Node('B', 'method', __FILE__, 2),
            new Node('C', 'method', __FILE__, 3),
        ];

        $edges = [
            new Edge('A::method', 'B::method', 'method'),
            new Edge('B::method', 'C::method', 'method'),
            new Edge('C::method', 'A::method', 'method'),
        ];

        $flow = new Flow($nodes, $edges);
        $detector = new CycleDetector($flow);

        $cycles = $detector->detectCycles();

        $this->assertCount(1, $cycles);
        $this->assertEquals(3, $cycles[0]['size']);
        $this->assertEquals('MEDIUM', $cycles[0]['severity']);
    }

    public function test_detects_multiple_separate_cycles(): void
    {
        // Ciclo 1: A ↔ B
        // Ciclo 2: C ↔ D
        $nodes = [
            new Node('A', 'method', __FILE__, 1),
            new Node('B', 'method', __FILE__, 2),
            new Node('C', 'method', __FILE__, 3),
            new Node('D', 'method', __FILE__, 4),
        ];

        $edges = [
            new Edge('A::method', 'B::method', 'method'),
            new Edge('B::method', 'A::method', 'method'),
            new Edge('C::method', 'D::method', 'method'),
            new Edge('D::method', 'C::method', 'method'),
        ];

        $flow = new Flow($nodes, $edges);
        $detector = new CycleDetector($flow);

        $cycles = $detector->detectCycles();

        $this->assertCount(2, $cycles);
    }

    public function test_detects_large_cycle(): void
    {
        // A → B → C → D → E → F → A (6 nodes)
        $nodes = [];
        $edges = [];
        
        $letters = ['A', 'B', 'C', 'D', 'E', 'F'];
        
        foreach ($letters as $i => $letter) {
            $nodes[] = new Node($letter, 'method', __FILE__, $i + 1);
        }
        
        foreach ($letters as $i => $letter) {
            $next = $letters[($i + 1) % count($letters)];
            $edges[] = new Edge("{$letter}::method", "{$next}::method", 'method');
        }

        $flow = new Flow($nodes, $edges);
        $detector = new CycleDetector($flow);

        $cycles = $detector->detectCycles();

        $this->assertCount(1, $cycles);
        $this->assertEquals(6, $cycles[0]['size']);
        $this->assertEquals('HIGH', $cycles[0]['severity']);
    }

    public function test_severity_classification(): void
    {
        // 2 nodes = LOW
        $nodes2 = [
            new Node('A', 'm', __FILE__, 1),
            new Node('B', 'm', __FILE__, 2),
        ];
        $edges2 = [
            new Edge('A::m', 'B::m', 'm'),
            new Edge('B::m', 'A::m', 'm'),
        ];
        $flow2 = new Flow($nodes2, $edges2);
        $cycles2 = (new CycleDetector($flow2))->detectCycles();
        $this->assertEquals('LOW', $cycles2[0]['severity']);

        // 4 nodes = MEDIUM
        $nodes4 = array_map(fn($l) => new Node($l, 'm', __FILE__, 1), ['A','B','C','D']);
        $edges4 = [
            new Edge('A::m', 'B::m', 'm'),
            new Edge('B::m', 'C::m', 'm'),
            new Edge('C::m', 'D::m', 'm'),
            new Edge('D::m', 'A::m', 'm'),
        ];
        $flow4 = new Flow($nodes4, $edges4);
        $cycles4 = (new CycleDetector($flow4))->detectCycles();
        $this->assertEquals('MEDIUM', $cycles4[0]['severity']);
    }

    public function test_stats(): void
    {
        // Criar um grafo com 1 ciclo
        $nodes = [
            new Node('A', 'method', __FILE__, 1),
            new Node('B', 'method', __FILE__, 2),
        ];

        $edges = [
            new Edge('A::method', 'B::method', 'method'),
            new Edge('B::method', 'A::method', 'method'),
        ];

        $flow = new Flow($nodes, $edges);
        $detector = new CycleDetector($flow);

        $stats = $detector->stats();

        $this->assertEquals(1, $stats['totalCycles']);
        $this->assertEquals(2, $stats['totalNodesInCycles']);
        $this->assertEquals(2, $stats['largestCycle']);
        $this->assertArrayHasKey('bySeverity', $stats);
    }

    public function test_are_in_cycle(): void
    {
        $nodes = [
            new Node('A', 'method', __FILE__, 1),
            new Node('B', 'method', __FILE__, 2),
            new Node('C', 'method', __FILE__, 3),
        ];

        $edges = [
            new Edge('A::method', 'B::method', 'method'),
            new Edge('B::method', 'A::method', 'method'),
            // C não está em ciclo
        ];

        $flow = new Flow($nodes, $edges);
        $detector = new CycleDetector($flow);

        $this->assertTrue($detector->areInCycle('A::method', 'B::method'));
        $this->assertFalse($detector->areInCycle('A::method', 'C::method'));
    }

    public function test_get_cycle_containing(): void
    {
        $nodes = [
            new Node('A', 'method', __FILE__, 1),
            new Node('B', 'method', __FILE__, 2),
        ];

        $edges = [
            new Edge('A::method', 'B::method', 'method'),
            new Edge('B::method', 'A::method', 'method'),
        ];

        $flow = new Flow($nodes, $edges);
        $detector = new CycleDetector($flow);

        $cycle = $detector->getCycleContaining('A::method');

        $this->assertNotNull($cycle);
        $this->assertEquals(2, $cycle['size']);
        $this->assertContains('A::method', $cycle['nodes']);
        $this->assertContains('B::method', $cycle['nodes']);
    }

    public function test_intra_class_cycle_gets_info_severity(): void
    {
        // Parser::parseExpr <-> Parser::parseAtom (mesma classe, recursao mutua)
        $nodes = [
            new Node('Parser', 'parseExpr', __FILE__, 1),
            new Node('Parser', 'parseAtom', __FILE__, 2),
        ];

        $edges = [
            new Edge('Parser::parseExpr', 'Parser::parseAtom', 'method'),
            new Edge('Parser::parseAtom', 'Parser::parseExpr', 'method'),
        ];

        $flow = new Flow($nodes, $edges);
        $detector = new CycleDetector($flow);

        $cycles = $detector->detectCycles();

        $this->assertCount(1, $cycles);
        $this->assertEquals('INFO', $cycles[0]['severity']);
        $this->assertEquals('intra-class mutual recursion', $cycles[0]['reason']);
    }

    public function test_cross_class_cycle_does_not_get_info_severity(): void
    {
        // ServiceA <-> ServiceB (classes diferentes)
        $nodes = [
            new Node('ServiceA', 'handle', __FILE__, 1),
            new Node('ServiceB', 'handle', __FILE__, 2),
        ];

        $edges = [
            new Edge('ServiceA::handle', 'ServiceB::handle', 'method'),
            new Edge('ServiceB::handle', 'ServiceA::handle', 'method'),
        ];

        $flow = new Flow($nodes, $edges);
        $detector = new CycleDetector($flow);

        $cycles = $detector->detectCycles();

        $this->assertCount(1, $cycles);
        $this->assertNotEquals('INFO', $cycles[0]['severity']);
        $this->assertNull($cycles[0]['reason']);
    }

    public function test_handles_disconnected_components(): void
    {
        // Grafo com componentes desconectados
        // A → B (sem ciclo)
        // C → D (sem ciclo)
        $nodes = [
            new Node('A', 'method', __FILE__, 1),
            new Node('B', 'method', __FILE__, 2),
            new Node('C', 'method', __FILE__, 3),
            new Node('D', 'method', __FILE__, 4),
        ];

        $edges = [
            new Edge('A::method', 'B::method', 'method'),
            new Edge('C::method', 'D::method', 'method'),
        ];

        $flow = new Flow($nodes, $edges);
        $detector = new CycleDetector($flow);

        $cycles = $detector->detectCycles();

        // Sem ciclos
        $this->assertCount(0, $cycles);
    }
}