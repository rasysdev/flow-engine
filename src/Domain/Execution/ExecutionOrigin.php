<?php

namespace FlowEngine\Domain\Execution;

enum ExecutionOrigin: string
{
    case CLI = 'cli';
    case HTTP = 'http';
    case GUIDED = 'guided';
    case AI = 'ai';
    case SYSTEM = 'system';
    case USER = 'user';
}
