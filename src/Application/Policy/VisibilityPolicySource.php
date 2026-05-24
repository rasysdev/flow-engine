<?php

namespace FlowEngine\Application\Policy;

enum VisibilityPolicySource: string
{
    case DEFAULT   = 'default';
    case FRAMEWORK = 'framework';
    case CONFIG    = 'config';
}
