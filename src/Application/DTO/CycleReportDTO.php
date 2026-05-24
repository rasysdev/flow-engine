<?php

namespace FlowEngine\Application\DTO;

/**
 * DTO readonly com relatorio de ciclos de dependencia.
 *
 * @api
 */
final readonly class CycleReportDTO
{
    /**
     * @param int $totalCycles Total de ciclos detectados
     * @param int $totalNodesInCycles Total de nodes envolvidos em ciclos
     * @param array<string, int> $bySeverity Contagem por severidade
     * @param int $largestCycle Tamanho do maior ciclo
     * @param array<int, array<string, mixed>> $cycles Lista de ciclos detectados
     */
    public function __construct(
        public int $totalCycles,
        public int $totalNodesInCycles,
        public array $bySeverity,
        public int $largestCycle,
        public array $cycles
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
            'totalCycles' => $this->totalCycles,
            'totalNodesInCycles' => $this->totalNodesInCycles,
            'bySeverity' => $this->bySeverity,
            'largestCycle' => $this->largestCycle,
            'cycles' => $this->cycles,
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
