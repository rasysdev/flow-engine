<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\Application\DTO\SolidReportDTO;
use FlowEngine\Domain\Analysis\SolidAnalyzer;
use FlowEngine\Domain\Contracts\FlowRepository;

/**
 * Analisa o projeto em busca de violações dos princípios SOLID.
 *
 * @api
 */
final class AnalyzeSolid
{
    public function __construct(
        private readonly FlowRepository $repository
    ) {
    }

    public function execute(): SolidReportDTO
    {
        $flow     = $this->repository->getFlow();
        $analyzer = new SolidAnalyzer($flow);
        $results  = $analyzer->analyze();

        $summary = [
            'SRP' => count($results['srp']),
            'ISP' => count($results['isp']),
            'DIP' => count($results['dip']),
            'OCP' => count($results['ocp']),
        ];

        $total = array_sum($summary);

        return new SolidReportDTO(
            totalIssues: $total,
            summary: $summary,
            srp: $results['srp'],
            isp: $results['isp'],
            dip: $results['dip'],
            ocp: $results['ocp'],
        );
    }
}
