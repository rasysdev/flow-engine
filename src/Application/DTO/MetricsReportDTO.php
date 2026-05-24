<?php

namespace FlowEngine\Application\DTO;

/**
 * DTO readonly com relatorio de metricas do grafo.
 *
 * @api
 */
final readonly class MetricsReportDTO
{
    /**
     * @param int $totalNodes Total de nodes
     * @param int $totalEdges Total de edges
     * @param float $avgFanIn Media de fan-in
     * @param float $avgFanOut Media de fan-out
     * @param int $maxFanIn Maior fan-in
     * @param int $maxFanOut Maior fan-out
     * @param int $hotspotCount Total de hotspots
     * @param array<int, array<string, mixed>> $hotspots Lista de hotspots
     * @param array<int, array<string, mixed>> $topCoupled Lista de mais acoplados
     */
    public function __construct(
        public int $totalNodes,
        public int $totalEdges,
        public float $avgFanIn,
        public float $avgFanOut,
        public int $maxFanIn,
        public int $maxFanOut,
        public int $hotspotCount,
        public array $hotspots,
        public array $topCoupled
    ) {
    }

    /**
     * Serializa para array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'totalNodes' => $this->totalNodes,
            'totalEdges' => $this->totalEdges,
            'avgFanIn' => $this->avgFanIn,
            'avgFanOut' => $this->avgFanOut,
            'maxFanIn' => $this->maxFanIn,
            'maxFanOut' => $this->maxFanOut,
            'hotspotCount' => $this->hotspotCount,
            'hotspots' => $this->hotspots,
            'topCoupled' => $this->topCoupled,
        ];
    }

    /**
     * Serializa para JSON.
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }
}
