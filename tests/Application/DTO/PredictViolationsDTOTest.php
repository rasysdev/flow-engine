<?php

namespace Tests\Application\DTO;

use FlowEngine\Application\DTO\PredictViolationsDTO;
use PHPUnit\Framework\TestCase;

final class PredictViolationsDTOTest extends TestCase
{
    private function violation(string $from, string $to, string $severity = 'HIGH'): array
    {
        return [
            'from'      => $from,
            'to'        => $to,
            'fromLayer' => 'Application',
            'toLayer'   => 'Infrastructure',
            'severity'  => $severity,
            'reason'    => 'Test violation',
        ];
    }

    private function makeDto(array $overrides = []): PredictViolationsDTO
    {
        $v = $this->violation('App\\UseCase::run', 'Infra\\Repo::find');
        return new PredictViolationsDTO(
            currentViolations:  $overrides['currentViolations']  ?? [$v],
            newViolations:      $overrides['newViolations']       ?? [$v],
            resolvedViolations: $overrides['resolvedViolations']  ?? [],
            totalCurrent:       $overrides['totalCurrent']        ?? 1,
            totalNew:           $overrides['totalNew']            ?? 1,
            totalResolved:      $overrides['totalResolved']       ?? 0,
            hasBaseline:        $overrides['hasBaseline']         ?? false,
            baselineLabel:      $overrides['baselineLabel']       ?? null,
            shouldFail:         $overrides['shouldFail']          ?? true,
            failOn:             $overrides['failOn']              ?? 'any',
            isClean:            $overrides['isClean']             ?? false,
        );
    }

    public function test_toArray_contains_all_fields(): void
    {
        $dto = $this->makeDto();
        $arr = $dto->toArray();

        $this->assertArrayHasKey('isClean', $arr);
        $this->assertArrayHasKey('shouldFail', $arr);
        $this->assertArrayHasKey('failOn', $arr);
        $this->assertArrayHasKey('hasBaseline', $arr);
        $this->assertArrayHasKey('baselineLabel', $arr);
        $this->assertArrayHasKey('totalCurrent', $arr);
        $this->assertArrayHasKey('totalNew', $arr);
        $this->assertArrayHasKey('totalResolved', $arr);
        $this->assertArrayHasKey('currentViolations', $arr);
        $this->assertArrayHasKey('newViolations', $arr);
        $this->assertArrayHasKey('resolvedViolations', $arr);
    }

    public function test_toJson_returns_valid_json(): void
    {
        $dto     = $this->makeDto();
        $decoded = json_decode($dto->toJson(), true);

        $this->assertIsArray($decoded);
        $this->assertSame('any', $decoded['failOn']);
        $this->assertSame(1, $decoded['totalCurrent']);
    }

    public function test_toArray_and_toJson_consistent(): void
    {
        $dto = $this->makeDto();
        $this->assertSame($dto->toArray(), json_decode($dto->toJson(), true));
    }

    public function test_clean_state(): void
    {
        $dto = $this->makeDto([
            'currentViolations' => [],
            'newViolations'     => [],
            'totalCurrent'      => 0,
            'totalNew'          => 0,
            'shouldFail'        => false,
            'isClean'           => true,
        ]);

        $this->assertTrue($dto->isClean);
        $this->assertFalse($dto->shouldFail);
    }

    public function test_with_baseline_fields(): void
    {
        $dto = $this->makeDto([
            'hasBaseline'   => true,
            'baselineLabel' => 'before-pr',
        ]);

        $this->assertTrue($dto->hasBaseline);
        $this->assertSame('before-pr', $dto->baselineLabel);
    }
}
