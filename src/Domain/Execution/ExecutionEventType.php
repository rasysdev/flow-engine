<?php

namespace FlowEngine\Domain\Execution;

enum ExecutionEventType: string
{
    case STARTED   = 'execution.started';
    case SUCCEEDED = 'execution.succeeded';
    case FAILED    = 'execution.failed';
}
