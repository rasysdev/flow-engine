<?php

namespace FlowEngine\Domain\Flow;

use FlowEngine\Domain\Contracts\Flow as FlowContract;

/**
 * Serviço de domain para tracing de fluxos no grafo.
 *
 * Realiza traversal BFS com:
 * - Adjacency list pré-computada (downstream e upstream)
 * - Profundidade configurável
 * - Filtro opcional de edge types
 * - Detecção de ciclos via visited set
 *
 * @api
 */
final class FlowTracer
{
    /** @var array<string, array{nodeId: string, edge: Edge}[]> */
    private array $downstreamAdj = [];

    /** @var array<string, array{nodeId: string, edge: Edge}[]> */
    private array $upstreamAdj = [];

    /** @var array<string, Node> */
    private array $nodeMap = [];

    public function __construct(
        private FlowContract $flow
    ) {
        $this->buildAdjacencyLists();
    }

    /**
     * Executa trace a partir de um nó inicial.
     *
     * @param string $startNodeId Nó inicial (Class::method)
     * @param TraceDirection $direction Direção do traversal
     * @param int $maxDepth Profundidade máxima (default: 10)
     * @param string[]|null $edgeTypeFilter Tipos de edge a seguir (null = todos)
     *
     * @return FlowTrace Resultado do trace
     *
     * @throws \InvalidArgumentException Se nó inicial não existir
     * @throws \InvalidArgumentException Se maxDepth for menor que 1
     */
    public function trace(
        string $startNodeId,
        TraceDirection $direction = TraceDirection::DOWNSTREAM,
        int $maxDepth = 10,
        ?array $edgeTypeFilter = null
    ): FlowTrace {
        if ($maxDepth < 1) {
            throw new \InvalidArgumentException('maxDepth must be at least 1');
        }

        $startNode = $this->flow->node($startNodeId);
        if ($startNode === null) {
            throw new \InvalidArgumentException("Node not found: {$startNodeId}");
        }

        $visited = [];
        $visitedNodes = [];
        $collectedEdges = [];
        $allPaths = [];

        // BFS
        // queue items: [nodeId, depth, currentPath]
        $queue = [[$startNodeId, 0, [$startNodeId]]];
        $visited[$startNodeId] = true;
        $visitedNodes[] = $startNode;

        while (!empty($queue)) {
            [$currentId, $currentDepth, $currentPath] = array_shift($queue);

            if ($currentDepth >= $maxDepth) {
                $allPaths[] = $currentPath;
                continue;
            }

            $neighbors = $this->getNeighbors($currentId, $direction, $edgeTypeFilter);

            if (empty($neighbors)) {
                $allPaths[] = $currentPath;
                continue;
            }

            $hasUnvisitedNeighbor = false;

            foreach ($neighbors as $neighbor) {
                $neighborId = $neighbor['nodeId'];
                $edge = $neighbor['edge'];

                $collectedEdges[$edge->from() . '->' . $edge->to()] = $edge;

                if (isset($visited[$neighborId])) {
                    continue;
                }

                $hasUnvisitedNeighbor = true;
                $visited[$neighborId] = true;

                if (isset($this->nodeMap[$neighborId])) {
                    $visitedNodes[] = $this->nodeMap[$neighborId];
                }

                $newPath = $currentPath;
                $newPath[] = $neighborId;

                $queue[] = [$neighborId, $currentDepth + 1, $newPath];
            }

            if (!$hasUnvisitedNeighbor && $currentId !== $startNodeId) {
                $allPaths[] = $currentPath;
            }
        }

        // If only the start node was found, add a single path
        if (empty($allPaths) && count($visitedNodes) === 1) {
            $allPaths[] = [$startNodeId];
        }

        return new FlowTrace(
            startNodeId: $startNodeId,
            direction: $direction,
            maxDepth: $maxDepth,
            nodes: $visitedNodes,
            edges: array_values($collectedEdges),
            paths: $allPaths
        );
    }

    /**
     * Extrai o conjunto de IDs do subgrafo a partir de um nó.
     *
     * Convenience method sobre trace() que devolve um lookup map de IDs
     * pronto para filtrar reports e elementos do grafo em O(1).
     * É a função canônica compartilhada por context export e diagram scoping.
     *
     * @param string $nodeId Nó inicial (Class::method)
     * @param int $depth Profundidade máxima (default: 5)
     * @param TraceDirection $direction Direção do traversal (default: BOTH)
     *
     * @return array<string, true> Mapa nodeId → true para lookup O(1)
     *
     * @throws \InvalidArgumentException Se o nó inicial não existir
     */
    public function extractSubgraph(
        string $nodeId,
        int $depth = 5,
        TraceDirection $direction = TraceDirection::BOTH
    ): array {
        $trace = $this->trace($nodeId, $direction, $depth);
        return array_fill_keys(array_map(fn($n) => $n->id(), $trace->nodes()), true);
    }

    /**
     * Retorna vizinhos de um nó na direção especificada.
     *
     * @param string $nodeId
     * @param TraceDirection $direction
     * @param string[]|null $edgeTypeFilter
     * @return array{nodeId: string, edge: Edge}[]
     */
    private function getNeighbors(string $nodeId, TraceDirection $direction, ?array $edgeTypeFilter): array
    {
        $neighbors = [];

        if ($direction === TraceDirection::DOWNSTREAM || $direction === TraceDirection::BOTH) {
            foreach ($this->downstreamAdj[$nodeId] ?? [] as $entry) {
                if ($edgeTypeFilter !== null && !in_array($entry['edge']->type(), $edgeTypeFilter, true)) {
                    continue;
                }
                $neighbors[] = $entry;
            }
        }

        if ($direction === TraceDirection::UPSTREAM || $direction === TraceDirection::BOTH) {
            foreach ($this->upstreamAdj[$nodeId] ?? [] as $entry) {
                if ($edgeTypeFilter !== null && !in_array($entry['edge']->type(), $edgeTypeFilter, true)) {
                    continue;
                }
                $neighbors[] = $entry;
            }
        }

        return $neighbors;
    }

    /**
     * Constrói adjacency lists downstream e upstream.
     */
    private function buildAdjacencyLists(): void
    {
        foreach ($this->flow->nodes() as $node) {
            $this->nodeMap[$node->id()] = $node;
        }

        foreach ($this->flow->edges() as $edge) {
            // Downstream: from → to
            $this->downstreamAdj[$edge->from()][] = [
                'nodeId' => $edge->to(),
                'edge' => $edge,
            ];

            // Upstream: to → from
            $this->upstreamAdj[$edge->to()][] = [
                'nodeId' => $edge->from(),
                'edge' => $edge,
            ];
        }
    }
}
