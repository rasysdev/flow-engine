<?php

namespace FlowEngine\Application\DTO;

/**
 * DTO readonly com relatorio de codigo orfao.
 *
 * @api
 */
final readonly class OrphanReportDTO
{
    /**
     * @param int $totalOrphans Total de orfaos
     * @param int $highConfidenceOrphans Total de orfaos com alta confianca
     * @param int $suspiciousLeafNodes Total de leaf nodes suspeitos
     * @param float $percentageOrphans Percentual de orfaos no projeto
     * @param array<int, array<string, mixed>> $orphans Lista de orfaos
     * @param array<int, array<string, mixed>> $suspiciousLeaves Lista de leaf nodes suspeitos
     */
    public function __construct(
        public int $totalOrphans,
        public int $highConfidenceOrphans,
        public int $suspiciousLeafNodes,
        public float $percentageOrphans,
        public array $orphans,
        public array $suspiciousLeaves
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
            'totalOrphans' => $this->totalOrphans,
            'highConfidenceOrphans' => $this->highConfidenceOrphans,
            'suspiciousLeafNodes' => $this->suspiciousLeafNodes,
            'percentageOrphans' => $this->percentageOrphans,
            'orphans' => $this->orphans,
            'suspiciousLeaves' => $this->suspiciousLeaves,
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
