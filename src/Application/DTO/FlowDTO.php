<?php

namespace FlowEngine\Application\DTO;

/**
 * DTO readonly representando o Flow completo (grafo de execução).
 * 
 * Contém nodes e edges em formato readonly.
 */
final readonly class FlowDTO
{
    public function __construct(
        public NodeCollectionDTO $nodes,
        /** @var EdgeDTO[] */
        public array $edges
    ) {
    }

    /**
     * Cria DTO a partir de entidade de Domain.
     * 
     * @param \FlowEngine\Domain\Flow\Flow $flow
     * @return self
     */
    public static function fromFlow(\FlowEngine\Domain\Flow\Flow $flow): self
    {
        return new self(
            nodes: NodeCollectionDTO::fromNodes($flow->nodes()),
            edges: array_map(
                fn($edge) => EdgeDTO::fromEdge($edge),
                $flow->edges()
            )
        );
    }

    /**
     * Busca node por ID.
     */
    public function findNode(string $id): ?NodeDTO
    {
        foreach ($this->nodes as $node) {
            if ($node->id === $id) {
                return $node;
            }
        }

        return null;
    }

    /**
     * Retorna apenas nodes públicos.
     */
    public function publicNodes(): NodeCollectionDTO
    {
        return $this->nodes->filter(fn(NodeDTO $n) => $n->isPublic);
    }

    /**
     * Conta total de nodes.
     */
    public function nodeCount(): int
    {
        return $this->nodes->count();
    }

    /**
     * Conta total de edges.
     */
    public function edgeCount(): int
    {
        return count($this->edges);
    }

    /**
     * Serializa para array.
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'nodes' => $this->nodes->toArray(),
            'edges' => array_map(
                fn(EdgeDTO $e) => $e->toArray(),
                $this->edges
            ),
            'stats' => [
                'nodeCount' => $this->nodeCount(),
                'edgeCount' => $this->edgeCount(),
            ],
        ];
    }

    /**
     * Serializa para JSON.
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }
}