<?php

namespace FlowEngine\Application\DTO;

/**
 * DTO readonly com relatorio de violacoes arquiteturais.
 *
 * @api
 */
final readonly class ArchitectureReportDTO
{
    /**
     * @param bool $isClean Indica se a arquitetura esta limpa
     * @param int $totalViolations Total de violacoes detectadas
     * @param array<string, int> $bySeverity Contagem por severidade
     * @param array<string, int> $byType Contagem por tipo
     * @param array<string, int> $layerDistribution Distribuicao por camada
     * @param array<int, array<string, mixed>> $violations Lista de violacoes
     */
    public function __construct(
        public bool $isClean,
        public int $totalViolations,
        public array $bySeverity,
        public array $byType,
        public array $layerDistribution,
        public array $violations
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
            'isClean' => $this->isClean,
            'totalViolations' => $this->totalViolations,
            'bySeverity' => $this->bySeverity,
            'byType' => $this->byType,
            'layerDistribution' => $this->layerDistribution,
            'violations' => $this->violations,
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
