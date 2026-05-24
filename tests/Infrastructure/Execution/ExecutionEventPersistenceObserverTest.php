<?php

namespace Tests\Infrastructure\Execution;

use FlowEngine\Domain\Execution\ExecutionContext;
use PHPUnit\Framework\TestCase;
use FlowEngine\Domain\Execution\ExecutionEvent;
use FlowEngine\Domain\Execution\ExecutionEventStore;
use FlowEngine\Infrastructure\Execution\Observability\ExecutionEventPersistenceObserver;

/**
 * @covers \FlowEngine\Infrastructure\Execution\Observability\ExecutionEventPersistenceObserver
 */
final class ExecutionEventPersistenceObserverTest extends TestCase
{
    /**
     * Garante que qualquer ExecutionEvent
     * é persistido via ExecutionEventStore.
     */
    public function test_it_persists_execution_event(): void
    {
        $store = $this->createMock(ExecutionEventStore::class);

        $store
            ->expects($this->once())
            ->method('append')
            ->with($this->isInstanceOf(ExecutionEvent::class));

        $observer = new ExecutionEventPersistenceObserver($store);

        $event = ExecutionEvent::succeeded(
            nodeId: 'Calculator::sum',
            inputs: [],
            output: null,
            durationMs: 1.2,
            context: ExecutionContext::system('test')
        );


        $observer->notify($event);
    }
}
