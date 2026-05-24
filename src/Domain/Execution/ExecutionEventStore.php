<?php

namespace FlowEngine\Domain\Execution;

/**
 * Contrato de persistência de eventos de execução.
 * O domínio só conhece este contrato.
 */
interface ExecutionEventStore
{
    public function append(ExecutionEvent $event): void;

    /**
     * Retorna todos os eventos armazenados.
     *
     * @return ExecutionEvent[]
     */
    public function all(): array;

    /**
     * Retorna eventos filtrados por nodeId.
     *
     * @param string $nodeId Identificador do nó (e.g. "Class::method")
     *
     * @return ExecutionEvent[]
     */
    public function forNode(string $nodeId): array;

    /**
     * Retorna eventos a partir de um timestamp.
     *
     * @param float $timestamp Timestamp UNIX (microtime)
     *
     * @return ExecutionEvent[]
     */
    public function since(float $timestamp): array;
}
