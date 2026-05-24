<?php

namespace FlowEngine\Domain\Contracts;

interface FlowSnapshotExporterPort
{
    /**
     * @return array<string, mixed>
     */
    public function export(Flow $flow): array;
}
