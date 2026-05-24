<?php

namespace FlowEngine\Domain\Execution;

interface ExecutionObserver
{
    public function notify(ExecutionEvent $event): void;
}
