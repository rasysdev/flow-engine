<?php

namespace FlowEngine\Domain\Execution;

enum ExecutionMode: string
{
    case AUTOMATIC = 'automatic';
    case GUIDED = 'guided';
}
