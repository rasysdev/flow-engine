<?php

namespace FlowEngine\Application\DTO;

/**
 * Relatório de violações SOLID detectadas no grafo.
 *
 * @api
 */
final readonly class SolidReportDTO
{
    /**
     * @param int                  $totalIssues Total de issues detectados (S + I + D + O)
     * @param array<string, int>   $summary     Contagem por princípio
     * @param array<int, array<string, mixed>> $srp Violações de Single Responsibility
     * @param array<int, array<string, mixed>> $isp Violações de Interface Segregation
     * @param array<int, array<string, mixed>> $dip Violações de Dependency Inversion
     * @param array<int, array<string, mixed>> $ocp Oportunidades de Open/Closed
     */
    public function __construct(
        public int   $totalIssues,
        public array $summary,
        public array $srp,
        public array $isp,
        public array $dip,
        public array $ocp,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'totalIssues' => $this->totalIssues,
            'summary'     => $this->summary,
            'srp'         => $this->srp,
            'isp'         => $this->isp,
            'dip'         => $this->dip,
            'ocp'         => $this->ocp,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }
}
