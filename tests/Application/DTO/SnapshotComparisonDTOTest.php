<?php

use PHPUnit\Framework\TestCase;
use FlowEngine\Application\DTO\SnapshotComparisonDTO;

final class SnapshotComparisonDTOTest extends TestCase
{
    private function createDTO(): SnapshotComparisonDTO
    {
        return new SnapshotComparisonDTO(
            baselineLabel: 'v1.0',
            currentLabel: 'current',
            metrics: ['totalNodes' => [10, 12, 2]],
            cycles: ['new' => [], 'resolved' => [], 'totalDelta' => 0],
            violations: ['new' => [], 'resolved' => [], 'totalDelta' => -1],
            orphans: ['new' => [], 'resolved' => ['App\\Old::unused'], 'totalDelta' => -1],
            complexity: ['improved' => [], 'degraded' => [], 'avgDelta' => -0.5],
            summary: ['improved' => 2, 'degraded' => 1, 'unchanged' => 4]
        );
    }

    public function test_construction(): void
    {
        $dto = $this->createDTO();

        $this->assertSame('v1.0', $dto->baselineLabel);
        $this->assertSame('current', $dto->currentLabel);
        $this->assertSame(2, $dto->summary['improved']);
    }

    public function test_to_array(): void
    {
        $dto = $this->createDTO();
        $array = $dto->toArray();

        $this->assertSame('v1.0', $array['baselineLabel']);
        $this->assertArrayHasKey('metrics', $array);
        $this->assertArrayHasKey('cycles', $array);
        $this->assertArrayHasKey('violations', $array);
        $this->assertArrayHasKey('orphans', $array);
        $this->assertArrayHasKey('complexity', $array);
        $this->assertArrayHasKey('summary', $array);
    }

    public function test_to_json(): void
    {
        $dto = $this->createDTO();
        $json = $dto->toJson();

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertSame('v1.0', $decoded['baselineLabel']);
        $this->assertSame('current', $decoded['currentLabel']);
    }
}
