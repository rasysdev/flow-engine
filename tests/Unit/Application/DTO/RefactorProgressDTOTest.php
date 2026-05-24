<?php

namespace Tests\Unit\Application\DTO;

use FlowEngine\Application\DTO\RefactorProgressDTO;
use PHPUnit\Framework\TestCase;

class RefactorProgressDTOTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $steps = [
            ['order' => 1, 'action' => 'Extract interface', 'target' => 'A::b', 'status' => 'done', 'completedAt' => '2026-02-18 10:00:00'],
            ['order' => 2, 'action' => 'Move class', 'target' => 'A::b', 'status' => 'pending', 'completedAt' => null],
        ];

        $dto = new RefactorProgressDTO(
            planLabel: 'my-plan',
            nodeId: 'MyClass::myMethod',
            totalSteps: 2,
            completedSteps: 1,
            currentStep: 2,
            steps: $steps,
            savedAt: '2026-02-18 10:01:00'
        );

        $this->assertSame('my-plan', $dto->planLabel);
        $this->assertSame('MyClass::myMethod', $dto->nodeId);
        $this->assertSame(2, $dto->totalSteps);
        $this->assertSame(1, $dto->completedSteps);
        $this->assertSame(2, $dto->currentStep);
        $this->assertCount(2, $dto->steps);
        $this->assertSame('2026-02-18 10:01:00', $dto->savedAt);
    }

    public function testCurrentStepNullWhenAllDone(): void
    {
        $dto = new RefactorProgressDTO(
            planLabel: 'done-plan',
            nodeId: 'A::b',
            totalSteps: 1,
            completedSteps: 1,
            currentStep: null,
            steps: [['order' => 1, 'action' => 'Do it', 'target' => 'A::b', 'status' => 'done', 'completedAt' => '2026-02-18 09:00:00']],
            savedAt: '2026-02-18 09:00:01'
        );

        $this->assertNull($dto->currentStep);
        $this->assertSame(1, $dto->completedSteps);
    }

    public function testToArrayContainsAllKeys(): void
    {
        $dto = $this->buildSample();

        $array = $dto->toArray();

        $this->assertArrayHasKey('planLabel', $array);
        $this->assertArrayHasKey('nodeId', $array);
        $this->assertArrayHasKey('totalSteps', $array);
        $this->assertArrayHasKey('completedSteps', $array);
        $this->assertArrayHasKey('currentStep', $array);
        $this->assertArrayHasKey('steps', $array);
        $this->assertArrayHasKey('savedAt', $array);
    }

    public function testToArraySerializesValuesCorrectly(): void
    {
        $dto = $this->buildSample();

        $array = $dto->toArray();

        $this->assertSame('test-plan', $array['planLabel']);
        $this->assertSame(3, $array['totalSteps']);
        $this->assertSame(1, $array['completedSteps']);
        $this->assertSame(2, $array['currentStep']);
        $this->assertCount(3, $array['steps']);
    }

    public function testToJsonProducesValidJson(): void
    {
        $dto = $this->buildSample();

        $json = $dto->toJson();

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertSame('test-plan', $decoded['planLabel']);
        $this->assertSame(3, $decoded['totalSteps']);
    }

    public function testStepsPreserveAllFields(): void
    {
        $dto = $this->buildSample();

        $firstStep = $dto->steps[0];
        $this->assertArrayHasKey('order', $firstStep);
        $this->assertArrayHasKey('action', $firstStep);
        $this->assertArrayHasKey('target', $firstStep);
        $this->assertArrayHasKey('status', $firstStep);
        $this->assertArrayHasKey('completedAt', $firstStep);
    }

    private function buildSample(): RefactorProgressDTO
    {
        return new RefactorProgressDTO(
            planLabel: 'test-plan',
            nodeId: 'SomeClass::handle',
            totalSteps: 3,
            completedSteps: 1,
            currentStep: 2,
            steps: [
                ['order' => 1, 'action' => 'Step one', 'target' => 'SomeClass::handle', 'status' => 'done', 'completedAt' => '2026-02-18 08:00:00'],
                ['order' => 2, 'action' => 'Step two', 'target' => 'SomeClass::handle', 'status' => 'pending', 'completedAt' => null],
                ['order' => 3, 'action' => 'Step three', 'target' => 'SomeClass::handle', 'status' => 'pending', 'completedAt' => null],
            ],
            savedAt: '2026-02-18 08:01:00'
        );
    }
}
