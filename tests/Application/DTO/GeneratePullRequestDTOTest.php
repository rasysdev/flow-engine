<?php

namespace Tests\Application\DTO;

use FlowEngine\Application\DTO\GeneratePullRequestDTO;
use PHPUnit\Framework\TestCase;

final class GeneratePullRequestDTOTest extends TestCase
{
    private function makeDto(array $overrides = []): GeneratePullRequestDTO
    {
        return new GeneratePullRequestDTO(
            title:              $overrides['title']              ?? 'refactor: MyClass::execute [HIGH, 3 steps]',
            body:               $overrides['body']               ?? '## Why this change?',
            branch:             $overrides['branch']             ?? 'refactor/myclass-execute',
            nodeId:             $overrides['nodeId']             ?? 'App\\MyClass::execute',
            riskLevel:          $overrides['riskLevel']          ?? 'HIGH',
            riskScore:          $overrides['riskScore']          ?? 75,
            stepsCount:         $overrides['stepsCount']         ?? 3,
            prerequisitesCount: $overrides['prerequisitesCount'] ?? 1,
            affectedNodes:      $overrides['affectedNodes']      ?? ['App\\Dep::run'],
            testingGuidance:    $overrides['testingGuidance']    ?? ['Run unit tests'],
            planLabel:          $overrides['planLabel']          ?? 'my-plan',
        );
    }

    public function test_toArray_contains_all_fields(): void
    {
        $dto = $this->makeDto();
        $arr = $dto->toArray();

        $this->assertSame('refactor: MyClass::execute [HIGH, 3 steps]', $arr['title']);
        $this->assertSame('## Why this change?', $arr['body']);
        $this->assertSame('refactor/myclass-execute', $arr['branch']);
        $this->assertSame('App\\MyClass::execute', $arr['nodeId']);
        $this->assertSame('HIGH', $arr['riskLevel']);
        $this->assertSame(75, $arr['riskScore']);
        $this->assertSame(3, $arr['stepsCount']);
        $this->assertSame(1, $arr['prerequisitesCount']);
        $this->assertSame(['App\\Dep::run'], $arr['affectedNodes']);
        $this->assertSame(['Run unit tests'], $arr['testingGuidance']);
        $this->assertSame('my-plan', $arr['planLabel']);
    }

    public function test_toJson_returns_valid_json(): void
    {
        $dto  = $this->makeDto();
        $json = $dto->toJson();

        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertSame('my-plan', $decoded['planLabel']);
    }

    public function test_toArray_and_toJson_are_consistent(): void
    {
        $dto = $this->makeDto();

        $fromArray = $dto->toArray();
        $fromJson  = json_decode($dto->toJson(), true);

        $this->assertSame($fromArray, $fromJson);
    }

    public function test_empty_affected_nodes_and_guidance_allowed(): void
    {
        $dto = $this->makeDto(['affectedNodes' => [], 'testingGuidance' => []]);
        $arr = $dto->toArray();

        $this->assertSame([], $arr['affectedNodes']);
        $this->assertSame([], $arr['testingGuidance']);
    }
}
