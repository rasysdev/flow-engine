<?php

namespace FlowEngine\Application\Policy;

enum VisibilityDecision
{
    case ALLOW;
    case DENY;
    case ABSTAIN;
}
