<?php

namespace FlowEngine\Application\DTO;

/**
 * DTO readonly com relatorio de complexidade ciclomatica.
 *
 * @api
 */
final readonly class ComplexityReportDTO
{
    /**
     * @param int $totalMethods Total de metodos analisados
     * @param float $avgComplexity Media de complexidade
     * @param int $maxComplexity Maior complexidade encontrada
     * @param int $minComplexity Menor complexidade encontrada
     * @param array<string, int> $byLevel Contagem por nivel
     * @param array<int, array<string, mixed>> $complexMethods Metodos complexos
     */
    public function __construct(
        public int $totalMethods,
        public float $avgComplexity,
        public int $maxComplexity,
        public int $minComplexity,
        public array $byLevel,
        public array $complexMethods
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
            'totalMethods' => $this->totalMethods,
            'avgComplexity' => $this->avgComplexity,
            'maxComplexity' => $this->maxComplexity,
            'minComplexity' => $this->minComplexity,
            'byLevel' => $this->byLevel,
            'complexMethods' => $this->complexMethods,
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
