<?php

namespace FlowEngine\Application\DTO;

/**
 * Relatório de padrões de projeto detectados no grafo.
 *
 * @api
 */
final readonly class PatternReportDTO
{
    /**
     * @param int                          $totalDetected Total de ocorrências detectadas
     * @param array<string, int>           $summary       Contagem por padrão
     * @param array<string, list<array<string, mixed>>> $patterns Ocorrências agrupadas por padrão
     */
    public function __construct(
        public int   $totalDetected,
        public array $summary,
        public array $patterns,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'totalDetected' => $this->totalDetected,
            'summary'       => $this->summary,
            'patterns'      => $this->patterns,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }
}
