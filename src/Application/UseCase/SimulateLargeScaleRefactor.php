<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\Application\DTO\LargeScaleSimulationDTO;
use FlowEngine\Application\DTO\SimulationNodeDTO;
use FlowEngine\Application\DTO\SimulationPhaseDTO;
use FlowEngine\Domain\Analysis\ArchitectureValidator;
use FlowEngine\Domain\Analysis\CycleDetector;
use FlowEngine\Domain\Analysis\MetricsAnalyzer;
use FlowEngine\Domain\Analysis\RiskScorer;
use FlowEngine\Domain\Contracts\Flow;
use FlowEngine\Domain\Contracts\FlowRepository;

/**
 * Simulates a large-scale refactoring across multiple nodes.
 *
 * Given a set of target nodes (selected by scope), the simulation:
 *   1. Scores each node's risk using existing Domain analysis.
 *   2. Builds a subgraph of edges within the target set.
 *   3. Applies Kahn's topological sort to produce ordered phases
 *      (dependencies are refactored before the nodes that call them).
 *   4. Detects nodes in cycles within the scope — they become a
 *      "Conflicting Dependencies" phase and are listed as conflicting pairs.
 *
 * No changes are executed. This is a planning artefact only.
 */
final class SimulateLargeScaleRefactor
{
    public function __construct(
        private FlowRepository $repository,
        private RiskScorer $riskScorer,
    ) {
    }

    /**
     * @param string   $scope         "hotspots:<N>", "namespace:<Ns>", "all", or "explicit"
     * @param string[] $explicitNodeIds Node IDs when scope is "explicit"
     */
    public function execute(string $scope, array $explicitNodeIds = []): LargeScaleSimulationDTO
    {
        $flow           = $this->repository->getFlow();
        $metricsAnalyzer = new MetricsAnalyzer($flow);
        $cycleDetector  = new CycleDetector($flow);
        $archValidator  = new ArchitectureValidator($flow);

        // 1. Collect target node IDs
        $targetIds = $this->collectTargetIds($scope, $explicitNodeIds, $flow, $metricsAnalyzer);

        if (empty($targetIds)) {
            return new LargeScaleSimulationDTO(
                phases: [],
                totalNodes: 0,
                totalRiskScore: 0,
                conflictingPairs: [],
                scope: $scope,
                metadata: [
                    'generatedAt'   => date('c'),
                    'phaseCount'    => 0,
                    'conflictCount' => 0,
                ],
            );
        }

        $targetSet = array_flip($targetIds); // O(1) membership lookup

        // 2. Pre-compute cycles and violations once (expensive full-graph traversals)
        $allCycles     = $cycleDetector->detectCycles();
        $allViolations = $archValidator->detectViolations();

        // 3. Build SimulationNodeDTOs — metrics + risk + in-scope callees
        $nodeMap = []; // nodeId => SimulationNodeDTO
        foreach ($targetIds as $nodeId) {
            $metrics = $metricsAnalyzer->metricsFor($nodeId);

            $cyclesCount = count(array_filter(
                $allCycles,
                fn(array $c) => in_array($nodeId, $c['nodes'], true)
            ));
            $violationsCount = count(array_filter(
                $allViolations,
                fn(array $v) => $v['from'] === $nodeId || $v['to'] === $nodeId
            ));

            $riskScore = $this->riskScorer->score($metrics, $cyclesCount, $violationsCount, 0);

            // dependsOn: unique callees within scope
            $dependsOn = [];
            foreach ($flow->edges() as $edge) {
                if ($edge->from() === $nodeId && $edge->to() !== $nodeId && isset($targetSet[$edge->to()])) {
                    $dependsOn[$edge->to()] = true;
                }
            }

            $nodeMap[$nodeId] = new SimulationNodeDTO(
                nodeId: $nodeId,
                riskScore: $riskScore->score,
                riskLevel: $riskScore->level,
                fanIn: $metrics->fanIn,
                fanOut: $metrics->fanOut,
                cyclesCount: $cyclesCount,
                violationsCount: $violationsCount,
                dependsOn: array_keys($dependsOn),
            );
        }

        // 4. Build unique in-scope edges (no self-loops, no duplicates)
        //    Edge direction: caller → callee (caller calls callee)
        $scopeEdgePairs = []; // "caller|callee" => [caller, callee]
        foreach ($flow->edges() as $edge) {
            $from = $edge->from();
            $to   = $edge->to();
            if (isset($targetSet[$from]) && isset($targetSet[$to]) && $from !== $to) {
                $scopeEdgePairs["$from|$to"] = [$from, $to];
            }
        }

        // 5. Build the ordering graph for Kahn's algorithm.
        //    Ordering rule: callee must be refactored before caller.
        //    So the "must-precede" edge goes: callee → caller.
        //    A node's in-degree = number of distinct callees it depends on within scope.
        $mustPrecedeAdj = array_fill_keys($targetIds, []); // callee → [callers]
        $inDegree       = array_fill_keys($targetIds, 0);  // caller → count of unresolved callees

        foreach ($scopeEdgePairs as [$caller, $callee]) {
            $mustPrecedeAdj[$callee][$caller] = true;
        }
        foreach ($mustPrecedeAdj as $callee => $callerMap) {
            foreach (array_keys($callerMap) as $caller) {
                $inDegree[$caller]++;
            }
            $mustPrecedeAdj[$callee] = array_keys($callerMap); // normalise to plain array
        }

        // 6. Kahn's algorithm → topological layers
        $layers    = [];
        $remaining = $inDegree; // mutable copy; nodes removed as they are placed
        $processed = [];

        while (count($processed) < count($targetIds)) {
            // Collect nodes with in-degree 0 that haven't been placed yet
            $layer = [];
            foreach ($remaining as $nodeId => $deg) {
                if ($deg === 0) {
                    $layer[] = $nodeId;
                }
            }

            if (empty($layer)) {
                // Remaining nodes are in cycles within scope — cannot be ordered
                break;
            }

            // Sort layer for determinism: highest risk first
            usort($layer, fn($a, $b) => $nodeMap[$b]->riskScore <=> $nodeMap[$a]->riskScore);

            $layers[] = $layer;

            foreach ($layer as $nodeId) {
                $processed[$nodeId] = true;
                unset($remaining[$nodeId]);
                foreach ($mustPrecedeAdj[$nodeId] as $dependent) {
                    if (isset($remaining[$dependent])) {
                        $remaining[$dependent]--;
                    }
                }
            }
        }

        // 7. Conflicting nodes = those still in $remaining after Kahn's terminates
        $conflictingNodeIds = array_keys($remaining);

        // Identify direct mutual pairs (A→B and B→A in the call graph)
        $conflictingPairs = [];
        $conflictSet      = array_flip($conflictingNodeIds);
        foreach ($scopeEdgePairs as [$a, $b]) {
            if (isset($conflictSet[$a]) && isset($conflictSet[$b]) && isset($scopeEdgePairs["$b|$a"])) {
                $key = $a < $b ? "$a||$b" : "$b||$a";
                $conflictingPairs[$key] = ['nodeA' => ($a < $b ? $a : $b), 'nodeB' => ($a < $b ? $b : $a)];
            }
        }
        $conflictingPairs = array_values($conflictingPairs);

        // 8. Build SimulationPhaseDTOs from topological layers
        $phases = [];
        $totalLayerCount = count($layers) + (empty($conflictingNodeIds) ? 0 : 1);

        foreach ($layers as $layerIndex => $layerNodeIds) {
            $phaseNumber = $layerIndex + 1;
            $phaseNodes  = array_map(fn($id) => $nodeMap[$id], $layerNodeIds);
            $totalRisk   = array_sum(array_map(fn($n) => $n->riskScore, $phaseNodes));

            [$label, $rationale] = $this->buildPhaseLabel($phaseNumber, $phaseNodes, $totalLayerCount);

            $phases[] = new SimulationPhaseDTO(
                phase: $phaseNumber,
                label: $label,
                rationale: $rationale,
                nodes: $phaseNodes,
                totalRiskScore: $totalRisk,
                nodeCount: count($phaseNodes),
            );
        }

        // Conflicting nodes go into a dedicated final phase
        if (!empty($conflictingNodeIds)) {
            $conflictPhaseNum = count($phases) + 1;
            $conflictNodes    = array_map(fn($id) => $nodeMap[$id], $conflictingNodeIds);
            $conflictRisk     = array_sum(array_map(fn($n) => $n->riskScore, $conflictNodes));

            $phases[] = new SimulationPhaseDTO(
                phase: $conflictPhaseNum,
                label: "Phase {$conflictPhaseNum}: Conflicting Dependencies",
                rationale: 'These nodes have mutual dependencies within the simulation scope and cannot be independently ordered. Refactor them as a coordinated unit.',
                nodes: $conflictNodes,
                totalRiskScore: $conflictRisk,
                nodeCount: count($conflictNodes),
            );
        }

        $totalRiskScore = array_sum(array_map(fn($p) => $p->totalRiskScore, $phases));

        return new LargeScaleSimulationDTO(
            phases: $phases,
            totalNodes: count($targetIds),
            totalRiskScore: $totalRiskScore,
            conflictingPairs: $conflictingPairs,
            scope: $scope,
            metadata: [
                'generatedAt'   => date('c'),
                'phaseCount'    => count($phases),
                'conflictCount' => count($conflictingNodeIds),
            ],
        );
    }

    /**
     * Collect target node IDs based on the requested scope.
     *
     * @param string[] $explicitNodeIds
     * @return string[]
     */
    private function collectTargetIds(
        string $scope,
        array $explicitNodeIds,
        Flow $flow,
        MetricsAnalyzer $metricsAnalyzer,
    ): array {
        if ($scope === 'explicit') {
            // Only include nodes that actually exist in the graph
            return array_values(array_filter(
                $explicitNodeIds,
                fn($id) => $flow->node($id) !== null
            ));
        }

        if ($scope === 'all') {
            return array_map(fn($n) => $n->id(), $flow->nodes());
        }

        if (str_starts_with($scope, 'hotspots:')) {
            $limit = max(1, (int) substr($scope, strlen('hotspots:')));
            return array_map(
                fn($m) => $m->nodeId,
                array_slice($metricsAnalyzer->hotspots(), 0, $limit)
            );
        }

        if (str_starts_with($scope, 'namespace:')) {
            $ns  = substr($scope, strlen('namespace:'));
            $ids = [];
            foreach ($flow->nodes() as $node) {
                if (str_starts_with($node->id(), $ns)) {
                    $ids[] = $node->id();
                }
            }
            return $ids;
        }

        return [];
    }

    /**
     * Build a human-readable label and rationale for a phase.
     *
     * @param SimulationNodeDTO[] $phaseNodes
     * @return array{string, string} [label, rationale]
     */
    private function buildPhaseLabel(int $phaseNumber, array $phaseNodes, int $totalPhases): array
    {
        if (empty($phaseNodes)) {
            return ["Phase {$phaseNumber}", 'Empty phase.'];
        }

        // Determine the dominant (highest) risk level present
        $riskOrder = ['CRITICAL' => 4, 'HIGH' => 3, 'MEDIUM' => 2, 'LOW' => 1];
        $dominant  = 'LOW';
        foreach ($phaseNodes as $node) {
            if (($riskOrder[$node->riskLevel] ?? 0) > ($riskOrder[$dominant] ?? 0)) {
                $dominant = $node->riskLevel;
            }
        }

        $label = match ($dominant) {
            'CRITICAL' => "Phase {$phaseNumber}: Critical-Risk Foundations",
            'HIGH'     => "Phase {$phaseNumber}: High-Risk Nodes",
            'MEDIUM'   => "Phase {$phaseNumber}: Medium-Risk Refactoring",
            default    => "Phase {$phaseNumber}: Low-Risk Cleanup",
        };

        if ($phaseNumber === 1) {
            $rationale = "Foundation nodes with no in-scope dependencies — safe to refactor independently. Dominant risk: {$dominant}.";
        } elseif ($phaseNumber === $totalPhases) {
            $rationale = "Final phase — depends on all prior phases being complete. Dominant risk: {$dominant}.";
        } else {
            $prev       = $phaseNumber - 1;
            $rationale  = "Refactor after Phase {$prev} is complete. Dominant risk: {$dominant}.";
        }

        return [$label, $rationale];
    }
}
