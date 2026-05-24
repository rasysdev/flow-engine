<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\Application\DTO\MetricsReportDTO;
use FlowEngine\Domain\Analysis\MetricsAnalyzer;
use FlowEngine\Domain\Contracts\FlowRepository;

final class AnalyzeMetrics
{
    public function __construct(
        private FlowRepository $repository
    ) {
    }

    public function execute(): MetricsReportDTO
    {
        $flow = $this->repository->getFlow();
        $analyzer = new MetricsAnalyzer($flow);

        $stats = $analyzer->stats();
        $hotspots = array_map(fn($m) => $m->toArray(), $analyzer->hotspots());
        $topCoupled = array_map(fn($m) => $m->toArray(), $analyzer->topCoupled());

        return new MetricsReportDTO(
            totalNodes: $stats['totalNodes'],
            totalEdges: $stats['totalEdges'],
            avgFanIn: $stats['avgFanIn'],
            avgFanOut: $stats['avgFanOut'],
            maxFanIn: $stats['maxFanIn'],
            maxFanOut: $stats['maxFanOut'],
            hotspotCount: $stats['hotspotCount'],
            hotspots: $hotspots,
            topCoupled: $topCoupled
        );
    }
}
