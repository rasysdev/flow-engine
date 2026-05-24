<?php

namespace FlowEngine\Application\DTO;

/**
 * Result of a large-scale refactoring simulation.
 *
 * The simulation divides the target node set into ordered phases and
 * detects intra-simulation ordering conflicts (mutual dependencies).
 * No changes are executed — this is a planning artefact only.
 *
 * @api
 */
final readonly class LargeScaleSimulationDTO
{
    /**
     * @param SimulationPhaseDTO[] $phases          Ordered refactoring phases (Phase 1 = most urgent)
     * @param int                  $totalNodes      Total number of nodes in the simulation
     * @param int                  $totalRiskScore  Aggregate risk score across all nodes
     * @param array<int, array{nodeA: string, nodeB: string}> $conflictingPairs
     *                                              Node pairs with mutual dependencies — ordering
     *                                              is ambiguous; both must be refactored together
     * @param string               $scope           How the target set was selected:
     *                                              "namespace:<Ns>", "hotspots:<N>", "explicit", "all"
     * @param array<string, mixed> $metadata        Generation metadata (timestamp, phaseCount, …)
     */
    public function __construct(
        public array  $phases,
        public int    $totalNodes,
        public int    $totalRiskScore,
        public array  $conflictingPairs,
        public string $scope,
        public array  $metadata,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'phases'           => array_map(fn($p) => $p->toArray(), $this->phases),
            'totalNodes'       => $this->totalNodes,
            'totalRiskScore'   => $this->totalRiskScore,
            'conflictingPairs' => $this->conflictingPairs,
            'scope'            => $this->scope,
            'metadata'         => $this->metadata,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }
}
