<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\Domain\Execution\ExecutionEventStore;
use FlowEngine\Domain\Execution\ExecutionObserver;

/**
 * Use case para replay de eventos de execução.
 *
 * Recupera eventos do store e notifica observers, permitindo
 * reconstruir estado ou gerar relatórios a partir de eventos históricos.
 */
final class ReplayExecutionEvents
{
    /** @var ExecutionObserver[] */
    private readonly array $observers;

    /**
     * @param ExecutionEventStore  $store     Store de eventos
     * @param ExecutionObserver[]  $observers Observers a notificar
     */
    public function __construct(
        private readonly ExecutionEventStore $store,
        array $observers
    ) {
        $this->observers = $observers;
    }

    /**
     * Replay de todos os eventos do store.
     *
     * @return int Número de eventos replayados
     */
    public function replayAll(): int
    {
        $events = $this->store->all();

        foreach ($events as $event) {
            $this->notifyAll($event);
        }

        return count($events);
    }

    /**
     * Replay de eventos de um nó específico.
     *
     * @param string $nodeId Identificador do nó
     *
     * @return int Número de eventos replayados
     */
    public function replayForNode(string $nodeId): int
    {
        $events = $this->store->forNode($nodeId);

        foreach ($events as $event) {
            $this->notifyAll($event);
        }

        return count($events);
    }

    /**
     * Replay de eventos a partir de um timestamp.
     *
     * @param float $timestamp Timestamp UNIX (microtime)
     *
     * @return int Número de eventos replayados
     */
    public function replaySince(float $timestamp): int
    {
        $events = $this->store->since($timestamp);

        foreach ($events as $event) {
            $this->notifyAll($event);
        }

        return count($events);
    }

    /**
     * Notifica todos os observers sobre um evento.
     *
     * @param \FlowEngine\Domain\Execution\ExecutionEvent $event
     */
    private function notifyAll(\FlowEngine\Domain\Execution\ExecutionEvent $event): void
    {
        foreach ($this->observers as $observer) {
            $observer->notify($event);
        }
    }
}
