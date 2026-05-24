<?php

namespace FlowEngine\Infrastructure\Execution\Observability;

use FlowEngine\Application\DTO\ExecutionMonitorSnapshot;
use FlowEngine\Domain\Execution\ExecutionEvent;
use FlowEngine\Domain\Execution\ExecutionEventType;
use FlowEngine\Domain\Execution\ExecutionObserver;

/**
 * Monitor de execução em tempo real.
 *
 * Implementa ExecutionObserver para coletar métricas agregadas:
 * - Contagem de sucesso/falha
 * - Duração média
 * - Taxa de sucesso
 * - Ring buffer de eventos recentes
 *
 * STARTED events são observados mas não contam como execuções finalizadas.
 */
final class ExecutionMonitor implements ExecutionObserver
{
    private int $successCount = 0;
    private int $failureCount = 0;
    private float $totalDurationMs = 0.0;

    /** @var ExecutionEvent[] Ring buffer de eventos recentes */
    private array $recentEvents = [];

    /**
     * @param int $maxRecentEvents Limite do ring buffer de eventos recentes
     */
    public function __construct(
        private readonly int $maxRecentEvents = 100
    ) {
    }

    /**
     * Recebe notificação de evento de execução.
     *
     * STARTED events são armazenados no ring buffer mas não contam
     * como execuções finalizadas para métricas.
     *
     * @param ExecutionEvent $event Evento de execução
     */
    public function notify(ExecutionEvent $event): void
    {
        // Adiciona ao ring buffer
        $this->recentEvents[] = $event;

        if (count($this->recentEvents) > $this->maxRecentEvents) {
            array_shift($this->recentEvents);
        }

        // Contabiliza apenas eventos finalizados
        match ($event->type) {
            ExecutionEventType::SUCCEEDED => $this->recordSuccess($event),
            ExecutionEventType::FAILED => $this->recordFailure($event),
            ExecutionEventType::STARTED => null, // Não conta como execução finalizada
        };
    }

    /**
     * Gera um snapshot imutável do estado atual do monitor.
     *
     * @return ExecutionMonitorSnapshot
     */
    public function snapshot(): ExecutionMonitorSnapshot
    {
        $total = $this->successCount + $this->failureCount;

        return new ExecutionMonitorSnapshot(
            totalExecutions: $total,
            successCount: $this->successCount,
            failureCount: $this->failureCount,
            averageDurationMs: $total > 0 ? $this->totalDurationMs / $total : 0.0,
            successRate: $total > 0 ? $this->successCount / $total : 0.0,
            lastEvents: $this->recentEvents
        );
    }

    /**
     * Reseta todas as métricas e o ring buffer.
     */
    public function reset(): void
    {
        $this->successCount = 0;
        $this->failureCount = 0;
        $this->totalDurationMs = 0.0;
        $this->recentEvents = [];
    }

    private function recordSuccess(ExecutionEvent $event): void
    {
        $this->successCount++;
        $this->totalDurationMs += $event->durationMs;
    }

    private function recordFailure(ExecutionEvent $event): void
    {
        $this->failureCount++;
        $this->totalDurationMs += $event->durationMs;
    }
}
