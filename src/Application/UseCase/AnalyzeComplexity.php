<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\Application\DTO\ComplexityReportDTO;
use FlowEngine\Domain\Analysis\ComplexityAnalyzer;
use FlowEngine\Domain\Contracts\FlowRepository;

final class AnalyzeComplexity
{
    public function __construct(
        private FlowRepository $repository
    ) {
    }

    public function execute(): ComplexityReportDTO
    {
        $flow = $this->repository->getFlow();
        $analyzer = new ComplexityAnalyzer($flow);

        $stats = $analyzer->stats();
        $complexMethods = [];

        foreach ($analyzer->findComplexMethods() as $nodeId => $data) {
            $complexMethods[] = [
                'nodeId' => $nodeId,
                'complexity' => $data['complexity'],
                'level' => $data['level'],
                'file' => $data['file'],
                'line' => $data['line'],
            ];
        }

        return new ComplexityReportDTO(
            totalMethods: $stats['total'],
            avgComplexity: $stats['avgComplexity'],
            maxComplexity: $stats['maxComplexity'],
            minComplexity: $stats['minComplexity'],
            byLevel: $stats['byLevel'],
            complexMethods: $complexMethods
        );
    }
}
