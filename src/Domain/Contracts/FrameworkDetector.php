<?php

namespace FlowEngine\Domain\Contracts;

use FlowEngine\Domain\Contracts\ProjectContext;
use FlowEngine\Domain\Framework\Framework;

interface FrameworkDetector
{
    public function detect(ProjectContext $context): Framework;
}
