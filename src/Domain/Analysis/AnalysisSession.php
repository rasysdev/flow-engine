<?php

namespace FlowEngine\Domain\Analysis;

/**
 * Entidade que agrupa operações de análise em uma sessão.
 *
 * Uma sessão representa todas as operações que um dev faz para resolver uma tarefa.
 * Permite comparar tempo e esforço entre análise manual, assistida e via AI.
 *
 * Modos:
 * - manual: dev usa grep/IDE
 * - assisted: dev usa Flow Engine
 * - ai: dev usa Flow Engine + AI
 *
 * @api
 */
final class AnalysisSession
{
    private float $endedAt = 0;

    /** @var AnalysisOperation[] */
    private array $operations = [];

    /**
     * @param string $id Identificador único
     * @param string $mode Modo da sessão (manual, assisted, ai)
     * @param string $goal Objetivo da sessão
     * @param float $startedAt Unix timestamp (microtime)
     */
    private function __construct(
        public readonly string $id,
        public readonly string $mode,
        public readonly string $goal,
        public readonly float $startedAt
    ) {
    }

    /**
     * Inicia uma nova sessão de análise.
     *
     * @param string $mode Modo da sessão (manual, assisted, ai)
     * @param string $goal Objetivo da sessão
     * @return self
     *
     * @throws \InvalidArgumentException Se mode for inválido
     */
    public static function start(string $mode, string $goal): self
    {
        $validModes = ['manual', 'assisted', 'ai'];
        if (!in_array($mode, $validModes, true)) {
            throw new \InvalidArgumentException(
                "Invalid session mode: '{$mode}'. Valid modes: " . implode(', ', $validModes)
            );
        }

        return new self(
            id: bin2hex(random_bytes(16)),
            mode: $mode,
            goal: $goal,
            startedAt: microtime(true)
        );
    }

    /**
     * Reconstrói sessão a partir de dados persistidos.
     *
     * @param string $id
     * @param string $mode
     * @param string $goal
     * @param float $startedAt
     * @param float $endedAt
     * @param AnalysisOperation[] $operations
     * @return self
     */
    public static function reconstruct(
        string $id,
        string $mode,
        string $goal,
        float $startedAt,
        float $endedAt,
        array $operations
    ): self {
        $session = new self($id, $mode, $goal, $startedAt);
        $session->endedAt = $endedAt;
        $session->operations = $operations;
        return $session;
    }

    /**
     * Registra uma operação na sessão.
     *
     * @param string $type Tipo de operação
     * @param string $detail Detalhes
     * @param float $durationMs Duração em ms
     *
     * @throws \LogicException Se sessão já foi finalizada
     */
    public function recordOperation(string $type, string $detail, float $durationMs): void
    {
        if (!$this->isActive()) {
            throw new \LogicException('Cannot record operations on an ended session');
        }

        $this->operations[] = new AnalysisOperation(
            type: $type,
            detail: $detail,
            durationMs: $durationMs,
            timestamp: microtime(true)
        );
    }

    /**
     * Finaliza a sessão.
     *
     * @throws \LogicException Se sessão já foi finalizada
     */
    public function end(): void
    {
        if (!$this->isActive()) {
            throw new \LogicException('Session already ended');
        }

        $this->endedAt = microtime(true);
    }

    /**
     * Duração total da sessão em milissegundos.
     *
     * @return float
     */
    public function totalDurationMs(): float
    {
        $end = $this->endedAt > 0 ? $this->endedAt : microtime(true);
        return ($end - $this->startedAt) * 1000;
    }

    /**
     * Número de operações registradas.
     *
     * @return int
     */
    public function operationCount(): int
    {
        return count($this->operations);
    }

    /**
     * Retorna todas as operações.
     *
     * @return AnalysisOperation[]
     */
    public function operations(): array
    {
        return $this->operations;
    }

    /**
     * Verifica se a sessão está ativa (não finalizada).
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->endedAt === 0.0;
    }

    /**
     * Timestamp de finalização (0 se ativa).
     *
     * @return float
     */
    public function endedAt(): float
    {
        return $this->endedAt;
    }
}
