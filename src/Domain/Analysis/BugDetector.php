<?php

namespace FlowEngine\Domain\Analysis;

use FlowEngine\Domain\Contracts\Flow;

/**
 * Weights raw bug findings from a scanner against the graph's impact data.
 *
 * Score formula: confidence × (1 + fanIn×2 + blastRadius/10), capped at 100.
 * When a node is not in the graph the fallback is: round(confidence × 20).
 */
final class BugDetector
{
    public function __construct(
        private Flow            $flow,
        private MetricsAnalyzer $metrics
    ) {}

    /**
     * @param array<int, array{nodeId:string, type:string, description:string, confidence:float, file:string, line:int|null}> $rawFindings
     * @return Bug[]
     */
    public function detectBugs(array $rawFindings, int $minScore = 0): array
    {
        $bugs = [];

        foreach ($rawFindings as $finding) {
            $nodeId     = $finding['nodeId'];
            $confidence = (float) $finding['confidence'];

            $bugScore = $this->score($nodeId, $confidence);

            if ($bugScore < $minScore) {
                continue;
            }

            $severity = Bug::severityFromScore($bugScore);

            $bugs[] = new Bug(
                nodeId:      $nodeId,
                type:        $finding['type'],
                description: $finding['description'],
                severity:    $severity,
                bugScore:    $bugScore,
                confidence:  $confidence,
                file:        $finding['file'],
                line:        $finding['line'],
            );
        }

        usort($bugs, fn(Bug $a, Bug $b) => $b->bugScore <=> $a->bugScore);

        return $bugs;
    }

    /**
     * @param Bug[] $bugs
     * @return array{totalBugs: int, bySeverity: array<string, int>, byType: array<string, int>}
     */
    public function stats(array $bugs): array
    {
        $bySeverity = ['CRITICAL' => 0, 'HIGH' => 0, 'MEDIUM' => 0, 'LOW' => 0];
        $byType     = [];

        foreach ($bugs as $bug) {
            $bySeverity[$bug->severity] = ($bySeverity[$bug->severity] ?? 0) + 1;
            $byType[$bug->type]         = ($byType[$bug->type] ?? 0) + 1;
        }

        return [
            'totalBugs'  => count($bugs),
            'bySeverity' => $bySeverity,
            'byType'     => $byType,
        ];
    }

    private function score(string $nodeId, float $confidence): int
    {
        $nodeIds = array_map(fn($n) => $n->id(), $this->flow->nodes());

        if (!in_array($nodeId, $nodeIds, true)) {
            return (int) round($confidence * 20);
        }

        $m           = $this->metrics->metricsFor($nodeId);
        $fanIn       = $m->fanIn;
        $blastRadius = $m->blastRadius;

        return (int) min(100, round($confidence * (1 + $fanIn * 2 + $blastRadius / 10)));
    }
}
