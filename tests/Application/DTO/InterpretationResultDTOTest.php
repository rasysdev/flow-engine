<?php

use PHPUnit\Framework\TestCase;
use FlowEngine\Application\DTO\InterpretationResultDTO;

final class InterpretationResultDTOTest extends TestCase
{
    public function test_readonly_properties(): void
    {
        $dto = new InterpretationResultDTO(
            type: 'cycles',
            interpretation: 'Found 2 cycles.',
            tokensUsed: 100,
            context: ['totalCycles' => 2],
            metadata: ['provider' => 'anthropic']
        );

        $this->assertSame('cycles', $dto->type);
        $this->assertSame('Found 2 cycles.', $dto->interpretation);
        $this->assertSame(100, $dto->tokensUsed);
        $this->assertSame(['totalCycles' => 2], $dto->context);
        $this->assertSame(['provider' => 'anthropic'], $dto->metadata);
    }

    public function test_to_array(): void
    {
        $dto = new InterpretationResultDTO(
            type: 'hotspots',
            interpretation: 'High complexity.',
            tokensUsed: 50,
            context: ['maxComplexity' => 15]
        );

        $array = $dto->toArray();

        $this->assertArrayHasKey('type', $array);
        $this->assertArrayHasKey('interpretation', $array);
        $this->assertArrayHasKey('tokensUsed', $array);
        $this->assertArrayHasKey('context', $array);
        $this->assertArrayHasKey('metadata', $array);
        $this->assertSame('hotspots', $array['type']);
    }

    public function test_to_json(): void
    {
        $dto = new InterpretationResultDTO(
            type: 'impact',
            interpretation: 'test',
            tokensUsed: 0,
            context: []
        );

        $json = $dto->toJson();

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertSame('impact', $decoded['type']);
    }

    public function test_default_metadata_is_empty(): void
    {
        $dto = new InterpretationResultDTO(
            type: 'violations',
            interpretation: 'Clean.',
            tokensUsed: 0,
            context: []
        );

        $this->assertSame([], $dto->metadata);
    }
}
