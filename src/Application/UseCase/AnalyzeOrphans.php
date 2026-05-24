<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\Application\DTO\OrphanReportDTO;
use FlowEngine\Domain\Analysis\MetricsAnalyzer;
use FlowEngine\Domain\Analysis\OrphanCodeDetector;
use FlowEngine\Domain\Contracts\FlowRepository;

final class AnalyzeOrphans
{
    /**
     * @param string[] $entrypointPatterns Custom patterns from flow-engine.json
     */
    public function __construct(
        private FlowRepository $repository,
        private array $entrypointPatterns = []
    ) {
    }

    public function execute(): OrphanReportDTO
    {
        $flow = $this->repository->getFlow();
        $metrics = new MetricsAnalyzer($flow);
        $detector = new OrphanCodeDetector($flow, $metrics, $this->entrypointPatterns);

        $stats = $detector->stats();
        $orphans = array_map(fn($o) => $o->toArray(), $detector->orphanMethods());
        $suspiciousLeaves = array_map(fn($o) => $o->toArray(), $detector->suspiciousLeafNodes());

        return new OrphanReportDTO(
            totalOrphans: $stats['totalOrphans'],
            highConfidenceOrphans: $stats['highConfidenceOrphans'],
            suspiciousLeafNodes: $stats['suspiciousLeafNodes'],
            percentageOrphans: $stats['percentageOrphans'],
            orphans: $orphans,
            suspiciousLeaves: $suspiciousLeaves
        );
    }
}
