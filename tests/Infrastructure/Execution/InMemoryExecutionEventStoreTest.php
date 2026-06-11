<?php

namespace Tests\Infrastructure\Execution;

use PHPUnit\Framework\TestCase;
use FlowEngine\Infrastructure\Execution\Observability\InMemoryExecutionEventStore;
use FlowEngine\Domain\Execution\ExecutionEvent;
use FlowEngine\Domain\Execution\ExecutionContext;

/**
 * @covers \FlowEngine\Infrastructure\Execution\Observability\InMemoryExecutionEventStore
 */
final class InMemoryExecutionEventStoreTest extends TestCase
{
    /**
     * Garante que um evento é armazenado via append.
     */
    public function test_it_appends_execution_event(): void
    {
        $store = new InMemoryExecutionEventStore();

        $event = ExecutionEvent::succeeded(
            nodeId: 'Calculator::sum',
            inputs: [1, 2],
            output: 3,
            durationMs: 1.2,
            context: ExecutionContext::system('test')
        );

        $store->append($event);

        $this->assertCount(1, $store->all());
        $this->assertSame($event, $store->all()[0]);
    }

    /**
     * Garante que múltiplos eventos são preservados em ordem.
     */
    public function test_it_preserves_event_order(): void
    {
        $store = new InMemoryExecutionEventStore();

        $event1 = ExecutionEvent::started(
            nodeId: 'A::x',
            inputs: [],
            context: ExecutionContext::system('test')
        );

        $event2 = ExecutionEvent::succeeded(
            nodeId: 'A::x',
            inputs: [],
            output: null,
            durationMs: 0.5,
            context: ExecutionContext::system('test')
        );

        $store->append($event1);
        $store->append($event2);

        $events = $store->all();

        $this->assertCount(2, $events);
        $this->assertSame($event1, $events[0]);
        $this->assertSame($event2, $events[1]);
    }

    /**
     * Garante que o store é append-only (nenhum evento é descartado).
     */
    public function test_store_is_append_only(): void
    {
        $store = new InMemoryExecutionEventStore();

        for ($i = 0; $i < 5; $i++) {
            $store->append(
                ExecutionEvent::succeeded(
                    nodeId: "Node::{$i}",
                    inputs: [],
                    output: null,
                    durationMs: 0.1,
                    context: ExecutionContext::system('test')
                )
            );
        }

        $this->assertCount(5, $store->all());
    }

    /**
     * Garante que forNode() filtra eventos por nodeId.
     */
    public function test_it_filters_events_by_node_id(): void
    {
        $store = new InMemoryExecutionEventStore();

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

        $store->append(ExecutionEvent::failed(
            nodeId: 'A::x',
            inputs: [],
            exception: new \RuntimeException('fail'),
            durationMs: 3.0,
            context: ExecutionContext::system('test')
        ));

        $filtered = $store->forNode('A::x');

        $this->assertCount(2, $filtered);
        $this->assertSame('A::x', $filtered[0]->nodeId);
        $this->assertSame('A::x', $filtered[1]->nodeId);
    }

    /**
     * Garante que since() filtra eventos a partir de um timestamp.
     */
    public function test_it_filters_events_since_timestamp(): void
    {
        $store = new InMemoryExecutionEventStore();

        $store->append(ExecutionEvent::succeeded(
            nodeId: 'A::x',
            inputs: [],
            output: null,
            durationMs: 1.0,
            context: ExecutionContext::system('test')
        ));

        // Wall-clock separation on both sides of the cutoff: microtime() may not
        // advance between adjacent calls, so space A strictly before the cutoff
        // and B strictly after it to keep the inclusive "since" boundary stable.
        usleep(1000);
        $cutoff = microtime(true);
        usleep(1000);

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
}
