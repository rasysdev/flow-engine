<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\Application\DTO\ChangeRiskDTO;
use FlowEngine\Domain\Analysis\ArchitectureValidator;
use FlowEngine\Domain\Analysis\ComplexityAnalyzer;
use FlowEngine\Domain\Analysis\CycleDetector;
use FlowEngine\Domain\Analysis\MetricsAnalyzer;
use FlowEngine\Domain\Analysis\RiskScorer;
use FlowEngine\Domain\Contracts\FlowRepository;

final class ScoreChangeRisk
{
    public function __construct(
        private RiskScorer $riskScorer,
        private FlowRepository $repository
    ) {
    }

    public function execute(string $nodeId): ChangeRiskDTO
    {
        $flow = $this->repository->getFlow();

        // Metrics
        $metricsAnalyzer = new MetricsAnalyzer($flow);
        $metrics = $metricsAnalyzer->metricsFor($nodeId);

        // Cycle count
        $cycleDetector = new CycleDetector($flow);
        $cycles = $cycleDetector->detectCycles();
        $cycleCount = count(array_filter(
            $cycles,
            fn(array $c) => in_array($nodeId, $c['nodes'], true)
        ));

        // Violation count
        $archValidator = new ArchitectureValidator($flow);
        $violations = $archValidator->detectViolations();
        $violationCount = count(array_filter(
            $violations,
            fn(array $v) => $v['from'] === $nodeId || $v['to'] === $nodeId
        ));

        // Cyclomatic complexity
        $cyclomaticComplexity = 0;
        $node = $flow->node($nodeId);
        if ($node !== null) {
            try {
                $complexityAnalyzer = new ComplexityAnalyzer($flow);
                $cyclomaticComplexity = $complexityAnalyzer->calculateMethodComplexity(
                    $node->file(),
                    $node->class(),
                    $node->method()
                );
            } catch (\Throwable) {
                $cyclomaticComplexity = 0;
            }
        }

        $riskScore = $this->riskScorer->score(
            $metrics,
            $cycleCount,
            $violationCount,
            $cyclomaticComplexity
        );

        return new ChangeRiskDTO(
            nodeId: $nodeId,
            score: $riskScore->score,
            level: $riskScore->level,
            factors: $riskScore->factors,
            metrics: $metrics->toArray()
        );
    }
}
