<?php

namespace FlowEngine\Domain\Contracts;

interface BugScannerPort
{
    /**
     * @return array<int, array{nodeId:string, type:string, description:string, confidence:float, file:string, line:int|null}>
     */
    public function scan(): array;
}
