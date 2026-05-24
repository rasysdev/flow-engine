<?php

use PHPUnit\Framework\TestCase;
use FlowEngine\Application\DTO\ChangeRiskDTO;

final class ChangeRiskDTOTest extends TestCase
{
    private function createDTO(): ChangeRiskDTO
    {
        return new ChangeRiskDTO(
            nodeId: 'App\\Service::handle',
            score: 45,
            level: 'MEDIUM',
            factors: [
                ['name' => 'blastRadius', 'weight' => 3.0, 'value' => 5.0, 'contribution' => 15.0],
                ['name' => 'fanIn', 'weight' => 2.0, 'value' => 3.0, 'contribution' => 6.0],
            ],
            metrics: [
                'nodeId' => 'App\\Service::handle',
                'fanIn' => 3,
                'fanOut' => 2,
                'blastRadius' => 5,
                'riskLevel' => 'LOW',
            ]
        );
    }

    public function test_construction(): void
    {
        $dto = $this->createDTO();

        $this->assertSame('App\\Service::handle', $dto->nodeId);
        $this->assertSame(45, $dto->score);
        $this->assertSame('MEDIUM', $dto->level);
        $this->assertCount(2, $dto->factors);
    }

    public function test_to_array(): void
    {
        $dto = $this->createDTO();
        $array = $dto->toArray();

        $this->assertSame('App\\Service::handle', $array['nodeId']);
        $this->assertSame(45, $array['score']);
        $this->assertSame('MEDIUM', $array['level']);
        $this->assertArrayHasKey('factors', $array);
        $this->assertArrayHasKey('metrics', $array);
    }

    public function test_to_json(): void
    {
        $dto = $this->createDTO();
        $json = $dto->toJson();

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertSame(45, $decoded['score']);
        $this->assertSame('MEDIUM', $decoded['level']);
    }
}
