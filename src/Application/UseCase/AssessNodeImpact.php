<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\Application\DTO\NodeImpactReportDTO;
use FlowEngine\Domain\Analysis\ArchitectureValidator;
use FlowEngine\Domain\Analysis\ComplexityAnalyzer;
use FlowEngine\Domain\Analysis\CycleDetector;
use FlowEngine\Domain\Analysis\MetricsAnalyzer;
use FlowEngine\Domain\Analysis\RiskScorer;
use FlowEngine\Domain\Contracts\FlowRepository;

final class AssessNodeImpact
{
    public function __construct(
        private AnalyzeImpact $analyzeImpact,
        private RiskScorer $riskScorer,
        private FlowRepository $repository
    ) {
    }

    public function execute(string $nodeId): NodeImpactReportDTO
    {
        $impact = $this->analyzeImpact->execute($nodeId);
        $upstream = $impact['impact']['upstream'];
        $downstream = $impact['impact']['downstream'];

        $flow = $this->repository->getFlow();

        // Metrics
        $metricsAnalyzer = new MetricsAnalyzer($flow);
        $metrics = $metricsAnalyzer->metricsFor($nodeId);

        // Cycles
        $cycleDetector = new CycleDetector($flow);
        $cycles = $cycleDetector->detectCycles();
        $cyclesInvolved = array_values(array_filter(
            $cycles,
            fn(array $cycle) => in_array($nodeId, $cycle['nodes'], true)
        ));

        // Architecture violations involving this node
        $archValidator = new ArchitectureValidator($flow);
        $allViolations = $archValidator->detectViolations();
        $violationsInvolved = array_values(array_filter(
            $allViolations,
            fn(array $v) => $v['from'] === $nodeId || $v['to'] === $nodeId
        ));

        // Cyclomatic complexity (default to 0 for non-parseable nodes)
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

        // Risk score
        $riskScore = $this->riskScorer->score(
            $metrics,
            count($cyclesInvolved),
            count($violationsInvolved),
            $cyclomaticComplexity
        );

        return new NodeImpactReportDTO(
            nodeId: $nodeId,
            upstream: $upstream,
            downstream: $downstream,
            blastRadius: $metrics->blastRadius,
            fanIn: $metrics->fanIn,
            fanOut: $metrics->fanOut,
            riskLevel: $riskScore->level,
            complexityScore: $metrics->complexityScore(),
            cyclesInvolved: $cyclesInvolved,
            violationsInvolved: $violationsInvolved,
            riskSummary: $riskScore->toArray()
        );
    }
}
