<?php

namespace Tests\Unit\Application\DTO;

use FlowEngine\Application\DTO\RefactorValidationDTO;
use PHPUnit\Framework\TestCase;

class RefactorValidationDTOTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $dto = new RefactorValidationDTO(
            nodeId: 'MyClass::myMethod',
            stepOrder: 3,
            passed: true,
            findings: ['Cycle resolved', 'Fan-in improved'],
            currentMetrics: ['fanIn' => 2, 'fanOut' => 4, 'blastRadius' => 6],
            baselineMetrics: ['fanIn' => 5, 'fanOut' => 4, 'blastRadius' => 10],
            recommendation: 'Proceed to the next step.'
        );

        $this->assertSame('MyClass::myMethod', $dto->nodeId);
        $this->assertSame(3, $dto->stepOrder);
        $this->assertTrue($dto->passed);
        $this->assertCount(2, $dto->findings);
        $this->assertSame(2, $dto->currentMetrics['fanIn']);
        $this->assertSame(5, $dto->baselineMetrics['fanIn']);
        $this->assertSame('Proceed to the next step.', $dto->recommendation);
    }

    public function testToArrayContainsAllKeys(): void
    {
        $dto = $this->buildSample();

        $array = $dto->toArray();

        $this->assertArrayHasKey('nodeId', $array);
        $this->assertArrayHasKey('stepOrder', $array);
        $this->assertArrayHasKey('passed', $array);
        $this->assertArrayHasKey('findings', $array);
        $this->assertArrayHasKey('currentMetrics', $array);
        $this->assertArrayHasKey('baselineMetrics', $array);
        $this->assertArrayHasKey('recommendation', $array);
    }

    public function testToArrayPassedFalseCase(): void
    {
        $dto = new RefactorValidationDTO(
            nodeId: 'A::b',
            stepOrder: 1,
            passed: false,
            findings: ['Cycle still present'],
            currentMetrics: ['fanIn' => 3, 'fanOut' => 2, 'blastRadius' => 8],
            baselineMetrics: ['fanIn' => 3, 'fanOut' => 2, 'blastRadius' => 8],
            recommendation: 'Review remaining circular dependencies.'
        );

        $array = $dto->toArray();

        $this->assertFalse($array['passed']);
        $this->assertSame(['Cycle still present'], $array['findings']);
    }

    public function testToJsonProducesValidJson(): void
    {
        $dto = $this->buildSample();

        $json = $dto->toJson();

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertSame('SomeClass::handle', $decoded['nodeId']);
        $this->assertTrue($decoded['passed']);
    }

    public function testCurrentAndBaselineMetricsPreserved(): void
    {
        $current = ['fanIn' => 1, 'fanOut' => 3, 'blastRadius' => 5];
        $baseline = ['fanIn' => 4, 'fanOut' => 3, 'blastRadius' => 10];

        $dto = new RefactorValidationDTO(
            nodeId: 'X::y',
            stepOrder: 2,
            passed: true,
            findings: [],
            currentMetrics: $current,
            baselineMetrics: $baseline,
            recommendation: 'Good.'
        );

        $this->assertSame($current, $dto->currentMetrics);
        $this->assertSame($baseline, $dto->baselineMetrics);
    }

    private function buildSample(): RefactorValidationDTO
    {
        return new RefactorValidationDTO(
            nodeId: 'SomeClass::handle',
            stepOrder: 1,
            passed: true,
            findings: ['Fan-in reduced from 5 to 2.'],
            currentMetrics: ['fanIn' => 2, 'fanOut' => 3, 'blastRadius' => 5],
            baselineMetrics: ['fanIn' => 5, 'fanOut' => 3, 'blastRadius' => 10],
            recommendation: 'Proceed to the next step.'
        );
    }
}
