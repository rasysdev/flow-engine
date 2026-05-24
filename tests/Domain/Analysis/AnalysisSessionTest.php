<?php

namespace Tests\Domain\Analysis;

use FlowEngine\Domain\Analysis\AnalysisSession;
use FlowEngine\Domain\Analysis\AnalysisOperation;
use PHPUnit\Framework\TestCase;

final class AnalysisSessionTest extends TestCase
{
    /** @test */
    public function test_session_tracks_operations(): void
    {
        $session = AnalysisSession::start('assisted', 'Trace order flow');

        $session->recordOperation('query', "FlowQuery::byNamespace('App\\Services')", 12.5);
        $session->recordOperation('trace', "TraceFlow::execute('OrderController::store')", 45.3);

        $this->assertEquals(2, $session->operationCount());

        $ops = $session->operations();
        $this->assertInstanceOf(AnalysisOperation::class, $ops[0]);
        $this->assertEquals('query', $ops[0]->type);
        $this->assertEquals(12.5, $ops[0]->durationMs);
        $this->assertEquals('trace', $ops[1]->type);
    }

    /** @test */
    public function test_session_calculates_total_duration(): void
    {
        $session = AnalysisSession::start('manual', 'Find dependencies');

        // Record some operations to take time
        $session->recordOperation('query', 'grep search', 100.0);

        $duration = $session->totalDurationMs();

        // Duration should be > 0 (session is still active)
        $this->assertGreaterThan(0, $duration);
    }

    /** @test */
    public function test_session_lifecycle_start_end(): void
    {
        $session = AnalysisSession::start('assisted', 'Analyze impact');

        $this->assertTrue($session->isActive());
        $this->assertNotEmpty($session->id);
        $this->assertEquals('assisted', $session->mode);
        $this->assertEquals('Analyze impact', $session->goal);

        $session->end();

        $this->assertFalse($session->isActive());
        $this->assertGreaterThan(0, $session->endedAt());
    }

    /** @test */
    public function test_session_modes(): void
    {
        $manual = AnalysisSession::start('manual', 'test');
        $this->assertEquals('manual', $manual->mode);

        $assisted = AnalysisSession::start('assisted', 'test');
        $this->assertEquals('assisted', $assisted->mode);

        $ai = AnalysisSession::start('ai', 'test');
        $this->assertEquals('ai', $ai->mode);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid session mode: 'invalid'");
        AnalysisSession::start('invalid', 'test');
    }

    /** @test */
    public function test_ended_session_rejects_operations(): void
    {
        $session = AnalysisSession::start('assisted', 'Test');
        $session->end();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot record operations on an ended session');

        $session->recordOperation('query', 'test', 10.0);
    }

    /** @test */
    public function test_ended_session_rejects_double_end(): void
    {
        $session = AnalysisSession::start('assisted', 'Test');
        $session->end();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Session already ended');

        $session->end();
    }

    /** @test */
    public function test_session_reconstruct(): void
    {
        $ops = [
            new AnalysisOperation('query', 'test query', 10.0, 1000.0),
        ];

        $session = AnalysisSession::reconstruct(
            id: 'abc123',
            mode: 'assisted',
            goal: 'Test goal',
            startedAt: 1000.0,
            endedAt: 1001.0,
            operations: $ops
        );

        $this->assertEquals('abc123', $session->id);
        $this->assertEquals('assisted', $session->mode);
        $this->assertEquals('Test goal', $session->goal);
        $this->assertFalse($session->isActive());
        $this->assertEquals(1, $session->operationCount());
    }
}
