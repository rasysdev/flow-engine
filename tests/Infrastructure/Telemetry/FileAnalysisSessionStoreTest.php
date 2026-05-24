<?php

namespace Tests\Infrastructure\Telemetry;

use FlowEngine\Domain\Analysis\AnalysisSession;
use FlowEngine\Infrastructure\Telemetry\FileAnalysisSessionStore;
use PHPUnit\Framework\TestCase;

final class FileAnalysisSessionStoreTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = sys_get_temp_dir() . '/flow-engine-test-sessions-' . uniqid() . '.jsonl';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    /** @test */
    public function test_session_store_saves_and_retrieves(): void
    {
        $store = new FileAnalysisSessionStore($this->tempFile);

        $session = AnalysisSession::start('assisted', 'Test goal');
        $session->recordOperation('query', 'test query', 10.5);
        $session->end();

        $store->save($session);

        $sessions = $store->all();

        $this->assertCount(1, $sessions);
        $this->assertEquals($session->id, $sessions[0]->id);
        $this->assertEquals('assisted', $sessions[0]->mode);
        $this->assertEquals('Test goal', $sessions[0]->goal);
        $this->assertEquals(1, $sessions[0]->operationCount());
    }

    /** @test */
    public function test_session_store_filters_by_mode(): void
    {
        $store = new FileAnalysisSessionStore($this->tempFile);

        $manual = AnalysisSession::start('manual', 'Manual task');
        $manual->end();
        $store->save($manual);

        $assisted = AnalysisSession::start('assisted', 'Assisted task');
        $assisted->end();
        $store->save($assisted);

        $manualSessions = $store->forMode('manual');
        $assistedSessions = $store->forMode('assisted');

        $this->assertCount(1, $manualSessions);
        $this->assertCount(1, $assistedSessions);
        $this->assertEquals('manual', $manualSessions[0]->mode);
        $this->assertEquals('assisted', $assistedSessions[0]->mode);
    }

    /** @test */
    public function test_session_store_returns_empty_for_missing_file(): void
    {
        $store = new FileAnalysisSessionStore('/tmp/nonexistent-' . uniqid() . '.jsonl');

        $this->assertEmpty($store->all());
    }

    /** @test */
    public function test_session_store_persists_operations(): void
    {
        $store = new FileAnalysisSessionStore($this->tempFile);

        $session = AnalysisSession::start('assisted', 'Complex analysis');
        $session->recordOperation('query', "FlowQuery::byNamespace('App')", 15.0);
        $session->recordOperation('trace', "TraceFlow::execute('Controller::store')", 42.0);
        $session->end();

        $store->save($session);

        $loaded = $store->all()[0];

        $this->assertEquals(2, $loaded->operationCount());
        $ops = $loaded->operations();
        $this->assertEquals('query', $ops[0]->type);
        $this->assertEquals('trace', $ops[1]->type);
        $this->assertEquals(15.0, $ops[0]->durationMs);
        $this->assertEquals(42.0, $ops[1]->durationMs);
    }
}
