<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\Application\DTO\PatternReportDTO;
use FlowEngine\Domain\Analysis\PatternDetector;
use FlowEngine\Domain\Contracts\FlowRepository;

/**
 * Detecta padrões de projeto (Design Patterns) no grafo do projeto.
 *
 * @api
 */
final class DetectPatterns
{
    public function __construct(
        private readonly FlowRepository $repository
    ) {
    }

    public function execute(): PatternReportDTO
    {
        $flow     = $this->repository->getFlow();
        $detector = new PatternDetector($flow);
        $patterns = $detector->detect();

        $summary = [];
        $total   = 0;

        foreach ($patterns as $patternName => $occurrences) {
            $count             = count($occurrences);
            $summary[$patternName] = $count;
            $total            += $count;
        }

        return new PatternReportDTO(
            totalDetected: $total,
            summary: $summary,
            patterns: $patterns,
        );
    }
}
