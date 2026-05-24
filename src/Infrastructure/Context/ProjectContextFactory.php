<?php

namespace FlowEngine\Infrastructure\Context;

use FlowEngine\Domain\Contracts\ProjectConfig;
use FlowEngine\Domain\Contracts\ProjectContext;

interface ProjectContextFactory
{
    public function create(ProjectConfig $config): ProjectContext;
}
