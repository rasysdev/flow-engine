<?php

namespace Tests\Unit\Application\DTO;

use FlowEngine\Application\DTO\RefactorGuidanceDTO;
use PHPUnit\Framework\TestCase;

class RefactorGuidanceDTOTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $dto = new RefactorGuidanceDTO(
            nodeId: 'MyClass::myMethod',
            stepOrder: 2,
            stepAction: 'Extract interface',
            stepTarget: 'MyClass::myMethod',
            actionableInstructions: ['Step A', 'Step B'],
            codePatterns: ['interface MyInterface {}'],
            warningsToAvoid: ['Do not break callers'],
            verificationChecklist: ['All tests pass'],
            estimatedEffort: '2 hours',
            metadata: ['tokensUsed' => 150, 'llmConfigured' => true]
        );

        $this->assertSame('MyClass::myMethod', $dto->nodeId);
        $this->assertSame(2, $dto->stepOrder);
        $this->assertSame('Extract interface', $dto->stepAction);
        $this->assertSame('MyClass::myMethod', $dto->stepTarget);
        $this->assertSame(['Step A', 'Step B'], $dto->actionableInstructions);
        $this->assertSame(['interface MyInterface {}'], $dto->codePatterns);
        $this->assertSame(['Do not break callers'], $dto->warningsToAvoid);
        $this->assertSame(['All tests pass'], $dto->verificationChecklist);
        $this->assertSame('2 hours', $dto->estimatedEffort);
        $this->assertSame(150, $dto->metadata['tokensUsed']);
    }

    public function testToArrayContainsAllKeys(): void
    {
        $dto = $this->buildSample();

        $array = $dto->toArray();

        $this->assertArrayHasKey('nodeId', $array);
        $this->assertArrayHasKey('stepOrder', $array);
        $this->assertArrayHasKey('stepAction', $array);
        $this->assertArrayHasKey('stepTarget', $array);
        $this->assertArrayHasKey('actionableInstructions', $array);
        $this->assertArrayHasKey('codePatterns', $array);
        $this->assertArrayHasKey('warningsToAvoid', $array);
        $this->assertArrayHasKey('verificationChecklist', $array);
        $this->assertArrayHasKey('estimatedEffort', $array);
        $this->assertArrayHasKey('metadata', $array);
    }

    public function testToArraySerializesValuesCorrectly(): void
    {
        $dto = $this->buildSample();

        $array = $dto->toArray();

        $this->assertSame('SomeClass::handle', $array['nodeId']);
        $this->assertSame(1, $array['stepOrder']);
        $this->assertSame('30 minutes', $array['estimatedEffort']);
        $this->assertCount(2, $array['actionableInstructions']);
    }

    public function testToJsonProducesValidJson(): void
    {
        $dto = $this->buildSample();

        $json = $dto->toJson();

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertSame('SomeClass::handle', $decoded['nodeId']);
        $this->assertSame(1, $decoded['stepOrder']);
    }

    public function testMetadataDefaultsToEmptyArray(): void
    {
        $dto = new RefactorGuidanceDTO(
            nodeId: 'A::b',
            stepOrder: 1,
            stepAction: 'Do something',
            stepTarget: 'A::b',
            actionableInstructions: [],
            codePatterns: [],
            warningsToAvoid: [],
            verificationChecklist: [],
            estimatedEffort: 'unknown'
        );

        $this->assertSame([], $dto->metadata);
    }

    private function buildSample(): RefactorGuidanceDTO
    {
        return new RefactorGuidanceDTO(
            nodeId: 'SomeClass::handle',
            stepOrder: 1,
            stepAction: 'Move to domain layer',
            stepTarget: 'SomeClass::handle',
            actionableInstructions: ['Move the class', 'Update namespace'],
            codePatterns: ['namespace App\\Domain;'],
            warningsToAvoid: ['Do not remove existing tests'],
            verificationChecklist: ['Tests pass', 'No violations'],
            estimatedEffort: '30 minutes',
            metadata: ['tokensUsed' => 200, 'llmConfigured' => true]
        );
    }
}
