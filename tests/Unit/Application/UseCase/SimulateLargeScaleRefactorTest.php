<?php

namespace Tests\Unit\Application\UseCase;

use FlowEngine\Application\DTO\LargeScaleSimulationDTO;
use FlowEngine\Application\UseCase\SimulateLargeScaleRefactor;
use FlowEngine\Domain\Analysis\RiskScorer;
use FlowEngine\Domain\Flow\Edge;
use FlowEngine\Domain\Flow\Node;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryFlowRepository;

final class SimulateLargeScaleRefactorTest extends TestCase
{
    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function makeUseCase(array $nodes = [], array $edges = []): SimulateLargeScaleRefactor
    {
        return new SimulateLargeScaleRefactor(
            new InMemoryFlowRepository($nodes, $edges),
            new RiskScorer(),
        );
    }

    private function node(string $class, string $method): Node
    {
        return new Node($class, $method, __FILE__, 1);
    }

    private function edge(string $from, string $to): Edge
    {
        return new Edge($from, $to, 'call', 'method_call');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Return type
    // ──────────────────────────────────────────────────────────────────────────

    public function testReturnsLargeScaleSimulationDTO(): void
    {
        $useCase = $this->makeUseCase([$this->node('App\\A', 'run')]);
        $result  = $useCase->execute('all');

        $this->assertInstanceOf(LargeScaleSimulationDTO::class, $result);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Empty result for empty scope
    // ──────────────────────────────────────────────────────────────────────────

    public function testEmptyResultWhenNoNodesMatchScope(): void
    {
        $useCase = $this->makeUseCase([$this->node('App\\A', 'run')]);
        $result  = $useCase->execute('namespace:Other\\');

        $this->assertSame(0, $result->totalNodes);
        $this->assertSame([], $result->phases);
        $this->assertSame([], $result->conflictingPairs);
    }

    public function testEmptyResultForExplicitScopeWithUnknownNodes(): void
    {
        $useCase = $this->makeUseCase([$this->node('App\\A', 'run')]);
        $result  = $useCase->execute('explicit', ['NonExistent::method']);

        $this->assertSame(0, $result->totalNodes);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Scope modes
    // ──────────────────────────────────────────────────────────────────────────

    public function testAllScopeIncludesEveryNode(): void
    {
        $nodes   = [$this->node('A', 'x'), $this->node('B', 'y'), $this->node('C', 'z')];
        $useCase = $this->makeUseCase($nodes);
        $result  = $useCase->execute('all');

        $this->assertSame(3, $result->totalNodes);
    }

    public function testNamespaceScopeFiltersCorrectly(): void
    {
        $nodes = [
            $this->node('App\\Svc\\ServiceA', 'run'),
            $this->node('App\\Svc\\ServiceB', 'run'),
            $this->node('Core\\Utils', 'helper'),
        ];
        $useCase = $this->makeUseCase($nodes);
        $result  = $useCase->execute('namespace:App\\Svc\\');

        $this->assertSame(2, $result->totalNodes);
        $this->assertSame('namespace:App\\Svc\\', $result->scope);
    }

    public function testExplicitScopeUsesGivenNodeIds(): void
    {
        $nodes = [
            $this->node('A', 'x'),
            $this->node('B', 'y'),
            $this->node('C', 'z'),
        ];
        $useCase = $this->makeUseCase($nodes);
        $result  = $useCase->execute('explicit', ['A::x', 'C::z']);

        $this->assertSame(2, $result->totalNodes);
        $this->assertSame('explicit', $result->scope);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Topological ordering (dependencies first)
    // ──────────────────────────────────────────────────────────────────────────

    public function testLinearDependencyProducesCorrectPhaseOrder(): void
    {
        // A calls B, B calls C  →  phase 1: C, phase 2: B, phase 3: A
        $nodes = [
            $this->node('App', 'a'),
            $this->node('App', 'b'),
            $this->node('App', 'c'),
        ];
        $edges = [
            $this->edge('App::a', 'App::b'),
            $this->edge('App::b', 'App::c'),
        ];

        $useCase = $this->makeUseCase($nodes, $edges);
        $result  = $useCase->execute('all');

        $this->assertGreaterThanOrEqual(2, count($result->phases));

        // C has no callees in scope → should be in phase 1
        $phase1NodeIds = array_map(fn($n) => $n->nodeId, $result->phases[0]->nodes);
        $this->assertContains('App::c', $phase1NodeIds);
    }

    public function testSingleNodeProducesOnePhase(): void
    {
        $useCase = $this->makeUseCase([$this->node('App\\X', 'go')]);
        $result  = $useCase->execute('all');

        $this->assertSame(1, count($result->phases));
        $this->assertSame(1, $result->phases[0]->nodeCount);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Conflicting pairs (mutual dependencies)
    // ──────────────────────────────────────────────────────────────────────────

    public function testMutualDependencyDetectedAsConflict(): void
    {
        // A calls B and B calls A — direct mutual dependency
        $nodes = [$this->node('A', 'x'), $this->node('B', 'y')];
        $edges = [
            $this->edge('A::x', 'B::y'),
            $this->edge('B::y', 'A::x'),
        ];

        $useCase = $this->makeUseCase($nodes, $edges);
        $result  = $useCase->execute('all');

        // Both nodes land in a conflicting phase
        $this->assertNotEmpty($result->conflictingPairs);
        $pair = $result->conflictingPairs[0];
        $nodeIds = [$pair['nodeA'], $pair['nodeB']];
        $this->assertContains('A::x', $nodeIds);
        $this->assertContains('B::y', $nodeIds);
    }

    public function testNoConflictsForLinearGraph(): void
    {
        $nodes = [$this->node('A', 'x'), $this->node('B', 'y')];
        $edges = [$this->edge('A::x', 'B::y')];

        $useCase = $this->makeUseCase($nodes, $edges);
        $result  = $useCase->execute('all');

        $this->assertSame([], $result->conflictingPairs);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // dependsOn field
    // ──────────────────────────────────────────────────────────────────────────

    public function testDependsOnContainsDirectCalleesWithinScope(): void
    {
        $nodes = [
            $this->node('App', 'caller'),
            $this->node('App', 'callee'),
        ];
        $edges = [$this->edge('App::caller', 'App::callee')];

        $useCase = $this->makeUseCase($nodes, $edges);
        $result  = $useCase->execute('all');

        // Find caller node DTO across all phases
        $callerNode = null;
        foreach ($result->phases as $phase) {
            foreach ($phase->nodes as $n) {
                if ($n->nodeId === 'App::caller') {
                    $callerNode = $n;
                }
            }
        }

        $this->assertNotNull($callerNode);
        $this->assertContains('App::callee', $callerNode->dependsOn);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Metadata and totals
    // ──────────────────────────────────────────────────────────────────────────

    public function testMetadataContainsExpectedKeys(): void
    {
        $useCase = $this->makeUseCase([$this->node('X', 'm')]);
        $result  = $useCase->execute('all');

        $this->assertArrayHasKey('generatedAt', $result->metadata);
        $this->assertArrayHasKey('phaseCount', $result->metadata);
        $this->assertArrayHasKey('conflictCount', $result->metadata);
    }

    public function testTotalRiskScoreIsSumOfPhaseRiskScores(): void
    {
        $nodes = [$this->node('A', 'x'), $this->node('B', 'y')];
        $useCase = $this->makeUseCase($nodes);
        $result  = $useCase->execute('all');

        $sumFromPhases = array_sum(array_map(fn($p) => $p->totalRiskScore, $result->phases));
        $this->assertSame($sumFromPhases, $result->totalRiskScore);
    }

    public function testResultSerializesToJson(): void
    {
        $useCase = $this->makeUseCase([$this->node('App\\Svc', 'run')]);
        $result  = $useCase->execute('all');

        $json    = $result->toJson();
        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('phases', $decoded);
        $this->assertArrayHasKey('totalNodes', $decoded);
        $this->assertSame(1, $decoded['totalNodes']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Hotspot scope (basic smoke test — hotspots require fan-in/fan-out)
    // ──────────────────────────────────────────────────────────────────────────

    public function testHotspotScopeReturnsSubsetOfHighCouplingNodes(): void
    {
        // Build a graph where one node has high fan-in
        $hub = $this->node('App\\Hub', 'handle');
        $callers = [];
        $edges   = [];
        for ($i = 1; $i <= 15; $i++) {
            $callers[] = $this->node("App\\Caller{$i}", 'call');
            $edges[]   = $this->edge("App\\Caller{$i}::call", 'App\\Hub::handle');
        }

        $useCase = $this->makeUseCase(array_merge([$hub], $callers), $edges);
        $result  = $useCase->execute('hotspots:5');

        // At most 5 nodes (the top hotspots)
        $this->assertLessThanOrEqual(5, $result->totalNodes);
        $this->assertSame('hotspots:5', $result->scope);
    }
}
