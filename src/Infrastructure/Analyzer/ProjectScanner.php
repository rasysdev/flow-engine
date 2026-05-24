<?php

namespace FlowEngine\Infrastructure\Analyzer;

use FlowEngine\Domain\Contracts\ProjectContext;

interface ProjectScanner
{
    /**
     * @return string[] Absolute file paths (by configured extensions)
     */
    public function scan(ProjectContext $context): array;
}
