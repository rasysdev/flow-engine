<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\Application\DTO\EdgeDTO;
use FlowEngine\Application\DTO\FlowTraceDTO;
use FlowEngine\Application\DTO\NodeCollectionDTO;
use FlowEngine\Domain\Contracts\FlowRepository;
use FlowEngine\Domain\Flow\FlowTracer;
use FlowEngine\Domain\Flow\TraceDirection;
use LogicException;

/**
 * Use case para tracing de fluxos no grafo.
 *
 * Descobre todo o fluxo a partir de um nó: controller → service → repository, etc.
 *
 * @api
 */
final class TraceFlow
{
    public function __construct(
        private FlowRepository $repository
    ) {
    }

    /**
     * Executa trace a partir de um nó inicial.
     *
     * @param string $startNodeId Nó inicial (Class::method)
     * @param string $direction 'downstream', 'upstream' ou 'both'
     * @param int $maxDepth Profundidade máxima (default: 10)
     * @param string[]|null $edgeTypes Tipos de edge a seguir (null = todos)
     *
     * @return FlowTraceDTO Resultado do trace
     *
     * @throws LogicException Se nó inicial não existir
     */
    public function execute(
        string $startNodeId,
        string $direction = 'downstream',
        int $maxDepth = 10,
        ?array $edgeTypes = null
    ): FlowTraceDTO {
        $flow = $this->repository->getFlow();

        if (!$flow->node($startNodeId)) {
            throw new LogicException("Node not found: {$startNodeId}");
        }

        $traceDirection = TraceDirection::fromString($direction);
        $tracer = new FlowTracer($flow);

        $trace = $tracer->trace($startNodeId, $traceDirection, $maxDepth, $edgeTypes);

        return new FlowTraceDTO(
            startNodeId: $trace->startNodeId(),
            direction: $traceDirection->value,
            maxDepth: $maxDepth,
            actualDepth: $trace->depth(),
            nodes: NodeCollectionDTO::fromNodes($trace->nodes()),
            edges: array_map(fn($e) => EdgeDTO::fromEdge($e), $trace->edges()),
            paths: $trace->paths()
        );
    }
}
