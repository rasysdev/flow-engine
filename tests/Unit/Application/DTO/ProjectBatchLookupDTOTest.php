<?php

declare(strict_types=1);

namespace Tests\Unit\Application\DTO;

use FlowEngine\Application\DTO\ProjectBatchLookupDTO;
use PHPUnit\Framework\TestCase;

final class ProjectBatchLookupDTOTest extends TestCase
{
    // Scenario 19: toArray() includes all expected fields
    public function test_to_array_includes_all_expected_fields(): void
    {
        $dto = $this->buildSample();
        $array = $dto->toArray();

        $this->assertArrayHasKey('kind', $array);
        $this->assertArrayHasKey('targetType', $array);
        $this->assertArrayHasKey('format', $array);
        $this->assertArrayHasKey('totalRequested', $array);
        $this->assertArrayHasKey('returned', $array);
        $this->assertArrayHasKey('truncated', $array);
        $this->assertArrayHasKey('results', $array);
        $this->assertArrayHasKey('notFound', $array);
        $this->assertArrayHasKey('droppedTargets', $array);
        $this->assertArrayHasKey('warnings', $array);
    }

    // Scenario 20: toJson() produces valid JSON
    public function test_to_json_produces_valid_json(): void
    {
        $dto = $this->buildSample();
        $json = $dto->toJson();

        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertSame('lookup_batch_result', $decoded['kind']);
        $this->assertSame('entrypoint', $decoded['targetType']);
    }

    // Scenario 21: results is array of arrays (each item serialized from ProjectLookupDTO.toArray())
    public function test_results_is_array_of_arrays(): void
    {
        $result = [
            'kind' => 'lookup_result',
            'targetType' => 'entrypoint',
            'target' => 'App\\Http\\OrderController::store',
            'whyItMatters' => 'Some text',
            'relatedAreas' => [],
            'directDependencies' => [],
            'directDependents' => [],
            'relevantBoundaries' => [],
            'recommendedReads' => [],
            'recommendedNextLookups' => [],
            'warnings' => [],
        ];

        $dto = new ProjectBatchLookupDTO(
            kind: 'lookup_batch_result',
            targetType: 'entrypoint',
            format: 'summary',
            totalRequested: 1,
            returned: 1,
            truncated: false,
            results: [$result],
            notFound: [],
            droppedTargets: [],
            warnings: [],
        );

        $array = $dto->toArray();
        $this->assertIsArray($array['results']);
        $this->assertCount(1, $array['results']);
        $this->assertIsArray($array['results'][0]);
        $this->assertSame('lookup_result', $array['results'][0]['kind']);
    }

    // metadata is omitted from toArray() when empty, included when non-empty
    public function test_metadata_omitted_when_empty(): void
    {
        $dto = $this->buildSample();
        $array = $dto->toArray();
        $this->assertArrayNotHasKey('metadata', $array);
    }

    public function test_metadata_included_when_non_empty(): void
    {
        $dto = new ProjectBatchLookupDTO(
            kind: 'lookup_batch_result',
            targetType: 'entrypoint',
            format: 'summary',
            totalRequested: 0,
            returned: 0,
            truncated: false,
            results: [],
            notFound: [],
            droppedTargets: [],
            warnings: [],
            metadata: ['configResolution' => ['mode' => 'explicit_config']],
        );

        $array = $dto->toArray();
        $this->assertArrayHasKey('metadata', $array);
        $this->assertSame('explicit_config', $array['metadata']['configResolution']['mode']);
    }

    public function test_truncated_and_dropped_targets_are_preserved(): void
    {
        $dto = new ProjectBatchLookupDTO(
            kind: 'lookup_batch_result',
            targetType: 'entrypoint',
            format: 'summary',
            totalRequested: 3,
            returned: 1,
            truncated: true,
            results: [['kind' => 'lookup_result']],
            notFound: [],
            droppedTargets: ['B', 'C'],
            warnings: [],
        );

        $array = $dto->toArray();
        $this->assertTrue($array['truncated']);
        $this->assertSame(['B', 'C'], $array['droppedTargets']);
        $this->assertSame(3, $array['totalRequested']);
        $this->assertSame(1, $array['returned']);
    }

    private function buildSample(): ProjectBatchLookupDTO
    {
        return new ProjectBatchLookupDTO(
            kind: 'lookup_batch_result',
            targetType: 'entrypoint',
            format: 'summary',
            totalRequested: 2,
            returned: 2,
            truncated: false,
            results: [
                ['kind' => 'lookup_result', 'targetType' => 'entrypoint', 'target' => 'A::run'],
                ['kind' => 'lookup_result', 'targetType' => 'entrypoint', 'target' => 'B::run'],
            ],
            notFound: [],
            droppedTargets: [],
            warnings: [],
        );
    }
}
