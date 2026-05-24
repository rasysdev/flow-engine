<?php

namespace FlowEngine\Bootstrap;

use FlowEngine\Domain\Contracts\ProjectConfig;
use FlowEngine\Domain\Contracts\ProjectContext;

final readonly class ProjectBootstrap
{
    public function __construct(
        public ProjectConfig $config,
        public ProjectContext $context,
        public ConfigResolution $configResolution,
    ) {
    }
}
