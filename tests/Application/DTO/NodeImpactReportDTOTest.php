<?php

use PHPUnit\Framework\TestCase;
use FlowEngine\Application\DTO\NodeImpactReportDTO;

final class NodeImpactReportDTOTest extends TestCase
{
    private function createDTO(): NodeImpactReportDTO
    {
        return new NodeImpactReportDTO(
            nodeId: 'App\\Service::handle',
            upstream: ['App\\Controller::index'],
            downstream: ['App\\Repository::find'],
            blastRadius: 5,
            fanIn: 3,
            fanOut: 2,
            riskLevel: 'MEDIUM',
            complexityScore: 42,
            cyclesInvolved: [],
            violationsInvolved: [],
            riskSummary: ['score' => 35, 'level' => 'MEDIUM', 'factors' => []]
        );
    }

    public function test_construction(): void
    {
        $dto = $this->createDTO();

        $this->assertSame('App\\Service::handle', $dto->nodeId);
        $this->assertSame(['App\\Controller::index'], $dto->upstream);
        $this->assertSame(['App\\Repository::find'], $dto->downstream);
        $this->assertSame(5, $dto->blastRadius);
        $this->assertSame(3, $dto->fanIn);
        $this->assertSame(2, $dto->fanOut);
        $this->assertSame('MEDIUM', $dto->riskLevel);
        $this->assertSame(42, $dto->complexityScore);
    }

    public function test_to_array(): void
    {
        $dto = $this->createDTO();
        $array = $dto->toArray();

        $this->assertSame('App\\Service::handle', $array['nodeId']);
        $this->assertSame(5, $array['blastRadius']);
        $this->assertArrayHasKey('riskSummary', $array);
        $this->assertArrayHasKey('cyclesInvolved', $array);
        $this->assertArrayHasKey('violationsInvolved', $array);
    }

    public function test_to_json(): void
    {
        $dto = $this->createDTO();
        $json = $dto->toJson();

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertSame('App\\Service::handle', $decoded['nodeId']);
    }

    public function test_with_cycles_and_violations(): void
    {
        $dto = new NodeImpactReportDTO(
            nodeId: 'App\\Core::process',
            upstream: [],
            downstream: [],
            blastRadius: 10,
            fanIn: 8,
            fanOut: 5,
            riskLevel: 'HIGH',
            complexityScore: 65,
            cyclesInvolved: [
                ['nodes' => ['App\\Core::process', 'App\\Service::handle'], 'size' => 2, 'severity' => 'LOW'],
            ],
            violationsInvolved: [
                ['from' => 'App\\Core::process', 'to' => 'Infra\\DB::query', 'severity' => 'CRITICAL'],
            ],
            riskSummary: ['score' => 70, 'level' => 'HIGH', 'factors' => []]
        );

        $this->assertCount(1, $dto->cyclesInvolved);
        $this->assertCount(1, $dto->violationsInvolved);

        $array = $dto->toArray();
        $this->assertCount(1, $array['cyclesInvolved']);
    }
}
