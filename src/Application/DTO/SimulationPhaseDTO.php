<?php

namespace FlowEngine\Application\DTO;

/**
 * One phase within a large-scale refactoring simulation.
 *
 * Nodes inside a phase should be refactored in the listed order —
 * the array is already topologically sorted (dependencies first).
 *
 * @api
 */
final readonly class SimulationPhaseDTO
{
    /**
     * @param int                  $phase          Phase number (1 = most urgent)
     * @param string               $label          Short human-readable label
     * @param string               $rationale      Why these nodes form a phase
     * @param SimulationNodeDTO[]  $nodes          Topologically ordered nodes
     * @param int                  $totalRiskScore Sum of node risk scores in this phase
     * @param int                  $nodeCount      Number of nodes in this phase
     */
    public function __construct(
        public int    $phase,
        public string $label,
        public string $rationale,
        public array  $nodes,
        public int    $totalRiskScore,
        public int    $nodeCount,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'phase'          => $this->phase,
            'label'          => $this->label,
            'rationale'      => $this->rationale,
            'nodes'          => array_map(fn($n) => $n->toArray(), $this->nodes),
            'totalRiskScore' => $this->totalRiskScore,
            'nodeCount'      => $this->nodeCount,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }
}
