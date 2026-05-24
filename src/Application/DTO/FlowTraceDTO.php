<?php

namespace FlowEngine\Application\DTO;

/**
 * DTO readonly representando o resultado de um trace no grafo.
 *
 * Contém o subgrafo descoberto: nós visitados, edges percorridas,
 * e caminhos do nó inicial até cada nó folha.
 *
 * @api
 */
final readonly class FlowTraceDTO
{
    /**
     * @param string $startNodeId Nó inicial do trace
     * @param string $direction Direção do traversal
     * @param int $maxDepth Profundidade máxima configurada
     * @param int $actualDepth Profundidade real atingida
     * @param NodeCollectionDTO $nodes Nós visitados
     * @param EdgeDTO[] $edges Edges percorridas
     * @param string[][] $paths Caminhos do nó inicial a cada folha
     */
    public function __construct(
        public string $startNodeId,
        public string $direction,
        public int $maxDepth,
        public int $actualDepth,
        public NodeCollectionDTO $nodes,
        public array $edges,
        public array $paths
    ) {
    }

    /**
     * Serializa para array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'startNodeId' => $this->startNodeId,
            'direction' => $this->direction,
            'maxDepth' => $this->maxDepth,
            'actualDepth' => $this->actualDepth,
            'nodeCount' => $this->nodes->count(),
            'edgeCount' => count($this->edges),
            'nodes' => $this->nodes->toArray(),
            'edges' => array_map(fn(EdgeDTO $e) => $e->toArray(), $this->edges),
            'paths' => $this->paths,
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
