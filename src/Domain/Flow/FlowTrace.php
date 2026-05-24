<?php

namespace FlowEngine\Domain\Flow;

/**
 * Resultado imutável de um trace no grafo.
 *
 * Contém o subgrafo descoberto: nós visitados, edges percorridas,
 * e caminhos do nó inicial até cada nó folha.
 *
 * @api
 */
final class FlowTrace
{
    /**
     * @param string $startNodeId Nó inicial do trace
     * @param TraceDirection $direction Direção do traversal
     * @param int $maxDepth Profundidade máxima configurada
     * @param Node[] $nodes Nós visitados (em ordem de visita)
     * @param Edge[] $edges Edges percorridas no subgrafo
     * @param string[][] $paths Caminhos do nó inicial a cada nó folha
     */
    public function __construct(
        private readonly string $startNodeId,
        private readonly TraceDirection $direction,
        private readonly int $maxDepth,
        private readonly array $nodes,
        private readonly array $edges,
        private readonly array $paths
    ) {
    }

    /**
     * @api
     */
    public function startNodeId(): string
    {
        return $this->startNodeId;
    }

    /**
     * @api
     */
    public function direction(): TraceDirection
    {
        return $this->direction;
    }

    /**
     * Profundidade máxima configurada.
     *
     * @api
     */
    public function maxDepth(): int
    {
        return $this->maxDepth;
    }

    /**
     * Nós visitados (em ordem de visita).
     *
     * @api
     * @return Node[]
     */
    public function nodes(): array
    {
        return $this->nodes;
    }

    /**
     * Edges percorridas no subgrafo.
     *
     * @api
     * @return Edge[]
     */
    public function edges(): array
    {
        return $this->edges;
    }

    /**
     * Caminhos do nó inicial a cada nó folha.
     *
     * @api
     * @return string[][]
     */
    public function paths(): array
    {
        return $this->paths;
    }

    /**
     * Profundidade real atingida.
     *
     * @api
     */
    public function depth(): int
    {
        if (empty($this->paths)) {
            return 0;
        }

        $maxLen = 0;
        foreach ($this->paths as $path) {
            $len = count($path) - 1; // depth = edges, not nodes
            if ($len > $maxLen) {
                $maxLen = $len;
            }
        }

        return $maxLen;
    }

    /**
     * @api
     */
    public function nodeCount(): int
    {
        return count($this->nodes);
    }

    /**
     * @api
     */
    public function edgeCount(): int
    {
        return count($this->edges);
    }
}
