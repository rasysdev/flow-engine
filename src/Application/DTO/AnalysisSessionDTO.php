<?php

namespace FlowEngine\Application\DTO;

use FlowEngine\Domain\Analysis\AnalysisSession;

/**
 * DTO readonly representando uma sessão de análise.
 *
 * @api
 */
final readonly class AnalysisSessionDTO
{
    /**
     * @param string $id Identificador da sessão
     * @param string $mode Modo (manual, assisted, ai)
     * @param string $goal Objetivo da sessão
     * @param float $totalDurationMs Duração total em ms
     * @param int $operationCount Número de operações
     * @param array $operations AnalysisOperationDTO-like arrays
     */
    public function __construct(
        public string $id,
        public string $mode,
        public string $goal,
        public float $totalDurationMs,
        public int $operationCount,
        public array $operations
    ) {
    }

    /**
     * Cria DTO a partir de entidade de Domain.
     *
     * @param AnalysisSession $session
     * @return self
     */
    public static function fromSession(AnalysisSession $session): self
    {
        $operations = array_map(fn($op) => [
            'type' => $op->type,
            'detail' => $op->detail,
            'durationMs' => $op->durationMs,
            'timestamp' => $op->timestamp,
        ], $session->operations());

        return new self(
            id: $session->id,
            mode: $session->mode,
            goal: $session->goal,
            totalDurationMs: $session->totalDurationMs(),
            operationCount: $session->operationCount(),
            operations: $operations
        );
    }

    /**
     * Serializa para array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'mode' => $this->mode,
            'goal' => $this->goal,
            'totalDurationMs' => $this->totalDurationMs,
            'operationCount' => $this->operationCount,
            'operations' => $this->operations,
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
