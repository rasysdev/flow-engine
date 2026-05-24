<?php

namespace FlowEngine\Infrastructure\Execution\Observability;

use FlowEngine\Domain\Execution\ExecutionEvent;
use FlowEngine\Domain\Execution\ExecutionObserver;
use Psr\Log\LoggerInterface;

final class ExecutionEventLogger implements ExecutionObserver
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    public function notify(ExecutionEvent $event): void
    {
        $context = [
            'nodeId'     => $event->nodeId,
            'durationMs'=> $event->durationMs,
        ];

        if ($event->exception) {
            $context['error'] = $event->exception->getMessage();
        }

        $this->logger->info(
            $event->type->value,
            $context
        );
    }
}
