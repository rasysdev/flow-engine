<?php

namespace FlowEngine\Application\InfraMap\Contract;

interface ProjectSectionAnalyzer
{
    /**
     * @return array<string, mixed>
     */
    public function analyze(string $projectRoot, string $mode = 'summary'): array;
}
