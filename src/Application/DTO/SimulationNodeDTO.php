<?php

namespace FlowEngine\Application\DTO;

/**
 * A single node inside a large-scale refactoring simulation.
 *
 * @api
 */
final readonly class SimulationNodeDTO
{
    /**
     * @param string   $nodeId          Node identifier (Class::method)
     * @param int      $riskScore       0-100 normalised risk score
     * @param string   $riskLevel       LOW | MEDIUM | HIGH | CRITICAL
     * @param int      $fanIn           Number of direct callers
     * @param int      $fanOut          Number of direct callees
     * @param int      $cyclesCount     Number of dependency cycles this node participates in
     * @param int      $violationsCount Number of architecture violations involving this node
     * @param string[] $dependsOn       Other nodes in the same simulation that should be
     *                                  refactored before this one (direct callees in scope)
     */
    public function __construct(
        public string $nodeId,
        public int    $riskScore,
        public string $riskLevel,
        public int    $fanIn,
        public int    $fanOut,
        public int    $cyclesCount,
        public int    $violationsCount,
        public array  $dependsOn,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'nodeId'          => $this->nodeId,
            'riskScore'       => $this->riskScore,
            'riskLevel'       => $this->riskLevel,
            'fanIn'           => $this->fanIn,
            'fanOut'          => $this->fanOut,
            'cyclesCount'     => $this->cyclesCount,
            'violationsCount' => $this->violationsCount,
            'dependsOn'       => $this->dependsOn,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }
}
