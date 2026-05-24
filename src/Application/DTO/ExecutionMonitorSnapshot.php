<?php

namespace FlowEngine\Application\DTO;

use FlowEngine\Domain\Execution\ExecutionEvent;

/**
 * Snapshot imutável do estado atual do ExecutionMonitor.
 *
 * Contém métricas agregadas e eventos recentes.
 */
final readonly class ExecutionMonitorSnapshot
{
    /**
     * @param int              $totalExecutions   Total de execuções finalizadas (succeeded + failed)
     * @param int              $successCount      Total de execuções com sucesso
     * @param int              $failureCount      Total de execuções com falha
     * @param float            $averageDurationMs Duração média em milissegundos
     * @param float            $successRate       Taxa de sucesso (0.0 a 1.0)
     * @param ExecutionEvent[] $lastEvents        Últimos eventos observados
     */
    public function __construct(
        public int $totalExecutions,
        public int $successCount,
        public int $failureCount,
        public float $averageDurationMs,
        public float $successRate,
        public array $lastEvents
    ) {
    }

    /**
     * Converte o snapshot para array associativo.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'totalExecutions' => $this->totalExecutions,
            'successCount' => $this->successCount,
            'failureCount' => $this->failureCount,
            'averageDurationMs' => $this->averageDurationMs,
            'successRate' => $this->successRate,
            'lastEventsCount' => count($this->lastEvents),
        ];
    }
}
