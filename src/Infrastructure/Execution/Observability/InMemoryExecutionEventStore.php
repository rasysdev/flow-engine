<?php

namespace FlowEngine\Infrastructure\Execution\Observability;

use FlowEngine\Domain\Execution\ExecutionEvent;
use FlowEngine\Domain\Execution\ExecutionEventStore;

/**
 * Store em memória para eventos de execução.
 *
 * - Append-only
 * - Preserva ordem de inserção
 * - Ideal para testes, debug e execução local
 */
final class InMemoryExecutionEventStore implements ExecutionEventStore
{
    /** @var ExecutionEvent[] */
    private array $events = [];

    public function append(ExecutionEvent $event): void
    {
        $this->events[] = $event;
    }

    /**
     * Retorna todos os eventos persistidos.
     *
     * @return ExecutionEvent[]
     */
    public function all(): array
    {
        return $this->events;
    }

    /**
     * Retorna eventos filtrados por nodeId.
     *
     * @param string $nodeId Identificador do nó
     *
     * @return ExecutionEvent[]
     */
    public function forNode(string $nodeId): array
    {
        return array_values(
            array_filter($this->events, fn(ExecutionEvent $e) => $e->nodeId === $nodeId)
        );
    }

    /**
     * Retorna eventos a partir de um timestamp.
     *
     * @param float $timestamp Timestamp UNIX (microtime)
     *
     * @return ExecutionEvent[]
     */
    public function since(float $timestamp): array
    {
        return array_values(
            array_filter($this->events, fn(ExecutionEvent $e) => $e->timestamp >= $timestamp)
        );
    }
}
