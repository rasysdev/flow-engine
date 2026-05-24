<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\Application\DTO\CycleReportDTO;
use FlowEngine\Domain\Analysis\CycleDetector;
use FlowEngine\Domain\Contracts\FlowRepository;

final class AnalyzeCycles
{
    public function __construct(
        private FlowRepository $repository
    ) {
    }

    public function execute(): CycleReportDTO
    {
        $flow = $this->repository->getFlow();
        $detector = new CycleDetector($flow);

        $stats = $detector->stats();
        $cycles = $detector->detectCycles();

        return new CycleReportDTO(
            totalCycles: $stats['totalCycles'],
            totalNodesInCycles: $stats['totalNodesInCycles'],
            bySeverity: $stats['bySeverity'],
            largestCycle: $stats['largestCycle'],
            cycles: $cycles
        );
    }
}
