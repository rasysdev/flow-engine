<?php

namespace FlowEngine\Infrastructure\Execution\Observability;

use FlowEngine\Domain\Execution\ExecutionEvent;
use FlowEngine\Domain\Execution\ExecutionEventStore;
use FlowEngine\Domain\Execution\ExecutionObserver;

/**
 * Observer responsável por persistir ExecutionEvents
 * em um storage durável.
 */
final class ExecutionEventPersistenceObserver implements ExecutionObserver
{
    public function __construct(
        private ExecutionEventStore $store
    ) {
    }

    public function notify(ExecutionEvent $event): void
    {
        $this->store->append($event);
    }
}
