<?php

namespace Tests\Infrastructure\Execution;

use PHPUnit\Framework\TestCase;
use FlowEngine\Infrastructure\Execution\Observability\FileExecutionEventStore;
use FlowEngine\Domain\Execution\ExecutionEvent;
use FlowEngine\Domain\Execution\ExecutionEventType;
use FlowEngine\Domain\Execution\ExecutionContext;
use FlowEngine\Domain\Execution\ExecutionMode;
use FlowEngine\Domain\Execution\ExecutionOrigin;

/**
 * @covers \FlowEngine\Infrastructure\Execution\Observability\FileExecutionEventStore
 */
final class FileExecutionEventStoreTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = sys_get_temp_dir() . '/flow-engine-test-' . uniqid() . '.jsonl';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function test_it_creates_file_on_first_append(): void
    {
        $this->assertFileDoesNotExist($this->tempFile);

        $store = new FileExecutionEventStore($this->tempFile);

        $store->append(ExecutionEvent::succeeded(
            nodeId: 'A::x',
            inputs: [],
            output: null,
            durationMs: 1.0,
            context: ExecutionContext::system('test')
        ));

        $this->assertFileExists($this->tempFile);
    }

    public function test_it_appends_event_as_json_line(): void
    {
        $store = new FileExecutionEventStore($this->tempFile);

        $store->append(ExecutionEvent::succeeded(
            nodeId: 'Calculator::sum',
            inputs: [1, 2],
            output: 3,
            durationMs: 1.5,
            context: ExecutionContext::system('test')
        ));

        $content = file_get_contents($this->tempFile);
        $lines = array_filter(explode("\n", $content));

        $this->assertCount(1, $lines);

        $data = json_decode($lines[0], true);
        $this->assertSame('execution.succeeded', $data['type']);
        $this->assertSame('Calculator::sum', $data['nodeId']);
        $this->assertSame([1, 2], $data['inputs']);
        $this->assertSame(3, $data['output']);
        $this->assertSame(1.5, $data['durationMs']);
    }

    public function test_it_preserves_event_order(): void
    {
        $store = new FileExecutionEventStore($this->tempFile);

        $store->append(ExecutionEvent::started(
            nodeId: 'A::x',
            inputs: [],
            context: ExecutionContext::system('test')
        ));

        $store->append(ExecutionEvent::succeeded(
            nodeId: 'A::x',
            inputs: [],
            output: 42,
            durationMs: 5.0,
            context: ExecutionContext::system('test')
        ));

        $events = $store->all();

        $this->assertCount(2, $events);
        $this->assertSame(ExecutionEventType::STARTED, $events[0]->type);
        $this->assertSame(ExecutionEventType::SUCCEEDED, $events[1]->type);
    }

    public function test_all_returns_empty_when_file_does_not_exist(): void
    {
        $store = new FileExecutionEventStore('/tmp/nonexistent-' . uniqid() . '.jsonl');

        $this->assertSame([], $store->all());
    }

    public function test_it_filters_events_by_node_id(): void
    {
        $store = new FileExecutionEventStore($this->tempFile);

        $store->append(ExecutionEvent::succeeded(
            nodeId: 'A::x',
            inputs: [],
            output: null,
            durationMs: 1.0,
            context: ExecutionContext::system('test')
        ));

        $store->append(ExecutionEvent::succeeded(
            nodeId: 'B::y',
            inputs: [],
            output: null,
            durationMs: 2.0,
            context: ExecutionContext::system('test')
        ));

        $store->append(ExecutionEvent::succeeded(
            nodeId: 'A::x',
            inputs: [],
            output: null,
            durationMs: 3.0,
            context: ExecutionContext::system('test')
        ));

        $filtered = $store->forNode('A::x');

        $this->assertCount(2, $filtered);
        $this->assertSame('A::x', $filtered[0]->nodeId);
        $this->assertSame('A::x', $filtered[1]->nodeId);
    }

    public function test_it_filters_events_since_timestamp(): void
    {
        $store = new FileExecutionEventStore($this->tempFile);

        $store->append(ExecutionEvent::succeeded(
            nodeId: 'A::x',
            inputs: [],
            output: null,
            durationMs: 1.0,
            context: ExecutionContext::system('test')
        ));

        // Read back the first event to get its timestamp
        $allBefore = $store->all();
        $cutoff = $allBefore[0]->timestamp + 0.001;

        usleep(10000); // 10ms delay to ensure timestamp difference

        $store->append(ExecutionEvent::succeeded(
            nodeId: 'B::y',
            inputs: [],
            output: null,
            durationMs: 2.0,
            context: ExecutionContext::system('test')
        ));

        $filtered = $store->since($cutoff);

        $this->assertCount(1, $filtered);
        $this->assertSame('B::y', $filtered[0]->nodeId);
    }

    public function test_it_serializes_and_deserializes_succeeded_event(): void
    {
        $store = new FileExecutionEventStore($this->tempFile);

        $store->append(ExecutionEvent::succeeded(
            nodeId: 'Service::process',
            inputs: ['key' => 'value'],
            output: ['result' => true],
            durationMs: 42.5,
            context: ExecutionContext::system('test')
        ));

        $events = $store->all();

        $this->assertCount(1, $events);
        $this->assertSame(ExecutionEventType::SUCCEEDED, $events[0]->type);
        $this->assertSame('Service::process', $events[0]->nodeId);
        $this->assertSame(['key' => 'value'], $events[0]->inputs);
        $this->assertSame(['result' => true], $events[0]->output);
        $this->assertSame(42.5, $events[0]->durationMs);
        $this->assertNull($events[0]->exception);
    }

    public function test_it_serializes_and_deserializes_failed_event(): void
    {
        $store = new FileExecutionEventStore($this->tempFile);

        $store->append(ExecutionEvent::failed(
            nodeId: 'Service::process',
            inputs: [],
            exception: new \RuntimeException('Something went wrong'),
            durationMs: 10.0,
            context: ExecutionContext::system('test')
        ));

        $events = $store->all();

        $this->assertCount(1, $events);
        $this->assertSame(ExecutionEventType::FAILED, $events[0]->type);
        $this->assertSame('Service::process', $events[0]->nodeId);
        $this->assertSame(10.0, $events[0]->durationMs);
        $this->assertNotNull($events[0]->exception);
        $this->assertSame('Something went wrong', $events[0]->exception->getMessage());
    }

    public function test_it_preserves_execution_context_when_deserializing(): void
    {
        $store = new FileExecutionEventStore($this->tempFile);
        $context = ExecutionContext::guided('guided execution of Service::process');

        $store->append(ExecutionEvent::succeeded(
            nodeId: 'Service::process',
            inputs: [],
            output: 'ok',
            durationMs: 12.0,
            context: $context
        ));

        $events = $store->all();

        $this->assertCount(1, $events);
        $this->assertNotNull($events[0]->context);
        $this->assertSame($context->id, $events[0]->context->id);
        $this->assertSame(ExecutionOrigin::USER, $events[0]->context->origin);
        $this->assertSame(ExecutionMode::GUIDED, $events[0]->context->mode);
        $this->assertSame('guided execution of Service::process', $events[0]->context->intent);
        $this->assertSame($context->startedAt, $events[0]->context->startedAt);
    }

    public function test_it_deserializes_legacy_events_without_context(): void
    {
        file_put_contents($this->tempFile, json_encode([
            'type' => 'execution.succeeded',
            'nodeId' => 'Legacy::run',
            'timestamp' => 123.0,
            'durationMs' => 4.0,
            'inputs' => [],
            'output' => null,
            'exception' => null,
        ], JSON_THROW_ON_ERROR) . "\n");

        $store = new FileExecutionEventStore($this->tempFile);
        $events = $store->all();

        $this->assertCount(1, $events);
        $this->assertNotNull($events[0]->context);
        $this->assertSame(ExecutionOrigin::SYSTEM, $events[0]->context->origin);
        $this->assertSame(ExecutionMode::AUTOMATIC, $events[0]->context->mode);
        $this->assertSame('replay', $events[0]->context->intent);
    }
}
