<?php

namespace Tests\Application\DTO;

use FlowEngine\Application\DTO\AnalysisSessionDTO;
use FlowEngine\Domain\Analysis\AnalysisSession;
use PHPUnit\Framework\TestCase;

final class AnalysisSessionDTOTest extends TestCase
{
    /** @test */
    public function test_session_dto_from_session(): void
    {
        $session = AnalysisSession::start('assisted', 'Test goal');
        $session->recordOperation('query', 'test query', 10.5);
        $session->end();

        $dto = AnalysisSessionDTO::fromSession($session);

        $this->assertEquals($session->id, $dto->id);
        $this->assertEquals('assisted', $dto->mode);
        $this->assertEquals('Test goal', $dto->goal);
        $this->assertEquals(1, $dto->operationCount);
        $this->assertGreaterThan(0, $dto->totalDurationMs);
    }

    /** @test */
    public function test_session_dto_serialization(): void
    {
        $session = AnalysisSession::start('manual', 'Serialize test');
        $session->recordOperation('trace', 'trace detail', 25.0);
        $session->end();

        $dto = AnalysisSessionDTO::fromSession($session);
        $array = $dto->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('mode', $array);
        $this->assertArrayHasKey('goal', $array);
        $this->assertArrayHasKey('totalDurationMs', $array);
        $this->assertArrayHasKey('operationCount', $array);
        $this->assertArrayHasKey('operations', $array);

        $json = $dto->toJson();
        $this->assertJson($json);
    }
}
