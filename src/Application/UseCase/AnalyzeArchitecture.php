<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\Application\DTO\ArchitectureReportDTO;
use FlowEngine\Domain\Analysis\ArchitectureValidator;
use FlowEngine\Domain\Contracts\FlowRepository;

final class AnalyzeArchitecture
{
    /**
     * @param array<string, string[]> $customLayers Layer name => namespace prefixes
     */
    public function __construct(
        private FlowRepository $repository,
        private array $customLayers = []
    ) {
    }

    public function execute(): ArchitectureReportDTO
    {
        $flow = $this->repository->getFlow();
        $validator = new ArchitectureValidator($flow, $this->customLayers);

        $stats = $validator->stats();
        $violations = $validator->detectViolations();

        return new ArchitectureReportDTO(
            isClean: $validator->isClean(),
            totalViolations: $stats['totalViolations'],
            bySeverity: $stats['bySeverity'],
            byType: $stats['byType'],
            layerDistribution: $validator->layerDistribution(),
            violations: $violations
        );
    }
}
