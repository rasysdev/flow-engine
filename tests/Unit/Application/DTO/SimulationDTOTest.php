<?php

namespace Tests\Unit\Application\DTO;

use FlowEngine\Application\DTO\LargeScaleSimulationDTO;
use FlowEngine\Application\DTO\SimulationNodeDTO;
use FlowEngine\Application\DTO\SimulationPhaseDTO;
use PHPUnit\Framework\TestCase;

class SimulationDTOTest extends TestCase
{
    // ──────────────────────────────────────────────────────────────────────────
    // SimulationNodeDTO
    // ──────────────────────────────────────────────────────────────────────────

    public function testSimulationNodeDTOConstructorSetsAllProperties(): void
    {
        $dto = new SimulationNodeDTO(
            nodeId: 'App\\Service::handle',
            riskScore: 72,
            riskLevel: 'HIGH',
            fanIn: 8,
            fanOut: 5,
            cyclesCount: 1,
            violationsCount: 2,
            dependsOn: ['App\\Repo::find', 'App\\Logger::log'],
        );

        $this->assertSame('App\\Service::handle', $dto->nodeId);
        $this->assertSame(72, $dto->riskScore);
        $this->assertSame('HIGH', $dto->riskLevel);
        $this->assertSame(8, $dto->fanIn);
        $this->assertSame(5, $dto->fanOut);
        $this->assertSame(1, $dto->cyclesCount);
        $this->assertSame(2, $dto->violationsCount);
        $this->assertCount(2, $dto->dependsOn);
    }

    public function testSimulationNodeDTOToArrayContainsAllKeys(): void
    {
        $dto = $this->buildSampleNode('App\\X::m', 10, 'LOW');

        $array = $dto->toArray();

        $this->assertArrayHasKey('nodeId', $array);
        $this->assertArrayHasKey('riskScore', $array);
        $this->assertArrayHasKey('riskLevel', $array);
        $this->assertArrayHasKey('fanIn', $array);
        $this->assertArrayHasKey('fanOut', $array);
        $this->assertArrayHasKey('cyclesCount', $array);
        $this->assertArrayHasKey('violationsCount', $array);
        $this->assertArrayHasKey('dependsOn', $array);
    }

    public function testSimulationNodeDTOToJsonProducesValidJson(): void
    {
        $dto  = $this->buildSampleNode('App\\X::m', 25, 'LOW');
        $json = $dto->toJson();

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertSame('App\\X::m', $decoded['nodeId']);
        $this->assertSame(25, $decoded['riskScore']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SimulationPhaseDTO
    // ──────────────────────────────────────────────────────────────────────────

    public function testSimulationPhaseDTOConstructorSetsAllProperties(): void
    {
        $nodes = [$this->buildSampleNode('A::b', 30, 'MEDIUM')];

        $dto = new SimulationPhaseDTO(
            phase: 1,
            label: 'Phase 1: Foundation',
            rationale: 'No dependencies.',
            nodes: $nodes,
            totalRiskScore: 30,
            nodeCount: 1,
        );

        $this->assertSame(1, $dto->phase);
        $this->assertSame('Phase 1: Foundation', $dto->label);
        $this->assertSame('No dependencies.', $dto->rationale);
        $this->assertCount(1, $dto->nodes);
        $this->assertSame(30, $dto->totalRiskScore);
        $this->assertSame(1, $dto->nodeCount);
    }

    public function testSimulationPhaseDTOToArraySerializesNodes(): void
    {
        $node  = $this->buildSampleNode('A::m', 15, 'LOW');
        $phase = new SimulationPhaseDTO(
            phase: 2,
            label: 'Phase 2',
            rationale: 'After phase 1.',
            nodes: [$node],
            totalRiskScore: 15,
            nodeCount: 1,
        );

        $array = $phase->toArray();

        $this->assertSame(2, $array['phase']);
        $this->assertCount(1, $array['nodes']);
        $this->assertIsArray($array['nodes'][0]);
        $this->assertSame('A::m', $array['nodes'][0]['nodeId']);
    }

    public function testSimulationPhaseDTOToJsonProducesValidJson(): void
    {
        $phase = new SimulationPhaseDTO(
            phase: 1,
            label: 'Phase 1',
            rationale: 'Root.',
            nodes: [],
            totalRiskScore: 0,
            nodeCount: 0,
        );

        $json    = $phase->toJson();
        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertSame(1, $decoded['phase']);
        $this->assertSame('Phase 1', $decoded['label']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // LargeScaleSimulationDTO
    // ──────────────────────────────────────────────────────────────────────────

    public function testLargeScaleSimulationDTOConstructorSetsAllProperties(): void
    {
        $phase = new SimulationPhaseDTO(1, 'P1', 'R1', [], 0, 0);

        $dto = new LargeScaleSimulationDTO(
            phases: [$phase],
            totalNodes: 5,
            totalRiskScore: 120,
            conflictingPairs: [['nodeA' => 'A::x', 'nodeB' => 'B::y']],
            scope: 'hotspots:5',
            metadata: ['generatedAt' => '2026-02-19T00:00:00+00:00', 'phaseCount' => 1, 'conflictCount' => 2],
        );

        $this->assertCount(1, $dto->phases);
        $this->assertSame(5, $dto->totalNodes);
        $this->assertSame(120, $dto->totalRiskScore);
        $this->assertCount(1, $dto->conflictingPairs);
        $this->assertSame('hotspots:5', $dto->scope);
        $this->assertSame(1, $dto->metadata['phaseCount']);
    }

    public function testLargeScaleSimulationDTOToArraySerializesPhases(): void
    {
        $node  = $this->buildSampleNode('X::y', 40, 'MEDIUM');
        $phase = new SimulationPhaseDTO(1, 'P1', 'R1', [$node], 40, 1);

        $dto   = new LargeScaleSimulationDTO(
            phases: [$phase],
            totalNodes: 1,
            totalRiskScore: 40,
            conflictingPairs: [],
            scope: 'all',
            metadata: ['generatedAt' => '2026-02-19T00:00:00+00:00', 'phaseCount' => 1, 'conflictCount' => 0],
        );

        $array = $dto->toArray();

        $this->assertArrayHasKey('phases', $array);
        $this->assertArrayHasKey('totalNodes', $array);
        $this->assertArrayHasKey('totalRiskScore', $array);
        $this->assertArrayHasKey('conflictingPairs', $array);
        $this->assertArrayHasKey('scope', $array);
        $this->assertArrayHasKey('metadata', $array);

        $this->assertIsArray($array['phases'][0]);
        $this->assertCount(1, $array['phases'][0]['nodes']);
    }

    public function testLargeScaleSimulationDTOToJsonProducesValidJson(): void
    {
        $dto = new LargeScaleSimulationDTO(
            phases: [],
            totalNodes: 0,
            totalRiskScore: 0,
            conflictingPairs: [],
            scope: 'all',
            metadata: ['generatedAt' => '2026-02-19T00:00:00+00:00', 'phaseCount' => 0, 'conflictCount' => 0],
        );

        $json    = $dto->toJson();
        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertSame('all', $decoded['scope']);
        $this->assertSame(0, $decoded['totalNodes']);
    }

    public function testLargeScaleSimulationDTOArrayJsonConsistency(): void
    {
        $node  = $this->buildSampleNode('A::b', 50, 'MEDIUM');
        $phase = new SimulationPhaseDTO(1, 'P1', 'R1', [$node], 50, 1);
        $dto   = new LargeScaleSimulationDTO([$phase], 1, 50, [], 'namespace:App', [
            'generatedAt' => '2026-02-19T00:00:00+00:00', 'phaseCount' => 1, 'conflictCount' => 0,
        ]);

        $fromArray = $dto->toArray();
        $fromJson  = json_decode($dto->toJson(), true);

        $this->assertSame($fromArray['totalNodes'], $fromJson['totalNodes']);
        $this->assertSame($fromArray['scope'], $fromJson['scope']);
        $this->assertSame($fromArray['phases'][0]['label'], $fromJson['phases'][0]['label']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function buildSampleNode(string $nodeId, int $riskScore, string $riskLevel): SimulationNodeDTO
    {
        return new SimulationNodeDTO(
            nodeId: $nodeId,
            riskScore: $riskScore,
            riskLevel: $riskLevel,
            fanIn: 2,
            fanOut: 3,
            cyclesCount: 0,
            violationsCount: 0,
            dependsOn: [],
        );
    }
}
