<?php

use PHPUnit\Framework\TestCase;
use FlowEngine\Application\DTO\SafetyAssessmentDTO;

final class SafetyAssessmentDTOTest extends TestCase
{
    private function createDTO(): SafetyAssessmentDTO
    {
        return new SafetyAssessmentDTO(
            nodeId: 'App\\Service::handle',
            affectedNodes: 5,
            cyclesAffected: [
                ['nodes' => ['App\\A::call', 'App\\B::call'], 'size' => 2, 'severity' => 'LOW'],
            ],
            violationsAffected: [
                ['from' => 'App\\X::call', 'to' => 'Infra\\Y::call', 'severity' => 'HIGH'],
            ],
            potentialOrphans: ['App\\Leaf::unused'],
            overallRisk: 'MEDIUM',
            recommendations: [
                'Add tests for downstream dependencies before refactoring',
                'Review cycle between A and B',
            ]
        );
    }

    public function test_construction(): void
    {
        $dto = $this->createDTO();

        $this->assertSame('App\\Service::handle', $dto->nodeId);
        $this->assertSame(5, $dto->affectedNodes);
        $this->assertSame('MEDIUM', $dto->overallRisk);
        $this->assertCount(1, $dto->cyclesAffected);
        $this->assertCount(1, $dto->violationsAffected);
        $this->assertCount(1, $dto->potentialOrphans);
        $this->assertCount(2, $dto->recommendations);
    }

    public function test_to_array(): void
    {
        $dto = $this->createDTO();
        $array = $dto->toArray();

        $this->assertSame('App\\Service::handle', $array['nodeId']);
        $this->assertSame(5, $array['affectedNodes']);
        $this->assertArrayHasKey('cyclesAffected', $array);
        $this->assertArrayHasKey('violationsAffected', $array);
        $this->assertArrayHasKey('potentialOrphans', $array);
        $this->assertArrayHasKey('overallRisk', $array);
        $this->assertArrayHasKey('recommendations', $array);
    }

    public function test_to_json(): void
    {
        $dto = $this->createDTO();
        $json = $dto->toJson();

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertSame('App\\Service::handle', $decoded['nodeId']);
        $this->assertSame('MEDIUM', $decoded['overallRisk']);
    }

    public function test_empty_assessment(): void
    {
        $dto = new SafetyAssessmentDTO(
            nodeId: 'App\\Safe::method',
            affectedNodes: 0,
            cyclesAffected: [],
            violationsAffected: [],
            potentialOrphans: [],
            overallRisk: 'LOW',
            recommendations: ['No concerns detected. Safe to refactor.']
        );

        $this->assertSame(0, $dto->affectedNodes);
        $this->assertSame('LOW', $dto->overallRisk);
    }
}
