<?php

namespace FlowEngine\Infrastructure\Execution;

use FlowEngine\Domain\Execution\ExecutionEvent;
use FlowEngine\Domain\Execution\ExecutionObserver;

final class LoggingExecutionObserver implements ExecutionObserver
{
    public function notify(ExecutionEvent $event): void
    {
        error_log(json_encode([
            'type' => $event->type->value,
            'node' => $event->nodeId,
            'origin' => $event->context->origin->value,
            'intent' => $event->context->intent,
            'timestamp' => $event->timestamp,
        ]));
    }
}
