<?php

namespace FlowEngine\Domain\Flow;

use FlowEngine\Domain\Contracts\Flow as FlowContract;

final class Flow implements FlowContract
{
    /** @var Node[] */
    private array $nodes;

    /** @var Edge[] */
    private array $edges;

    private SymbolIndex $symbolIndex;

    public function __construct(array $nodes, array $edges, ?SymbolIndex $symbols = null)
    {
        $this->nodes       = $nodes;
        $this->edges       = $edges;
        $this->symbolIndex = $symbols ?? new SymbolIndex();
    }

    /**
     * Retorna todos os nodes (entidades de Domain).
     * 
     * @internal Uso interno apenas
     * @return Node[]
     */
    public function nodes(): array
    {
        return $this->nodes;
    }

    /**
     * Retorna todas as edges.
     * @api
     * @return Edge[]
     */
    public function edges(): array
    {
        return $this->edges;
    }

    /**
     * Busca node por ID (retorna entidade de Domain).
     * 
     * @internal Uso interno apenas
     * @param string $id
     * @return Node|null
     */
    public function node(string $id): ?Node
    {
        foreach ($this->nodes as $node) {
            if ($node->id() === $id) {
                return $node;
            }
        }

        return null;
    }

    public function findNode(string $id): ?Node
    {
        return $this->node($id);
    }

    /**
     * Inicia query builder.
     * @api
     * @return FlowQuery
     */
    public function query(): FlowQuery
    {
        return new FlowQuery($this);
    }

    /**
     * Conta total de nodes.
     * @api
     */
    public function nodeCount(): int
    {
        return count($this->nodes);
    }

    /**
     * Conta total de edges.
     * @api
     */
    public function edgeCount(): int
    {
        return count($this->edges);
    }

    /**
     * Returns the symbol index for this flow (imports, exports, top-level identifiers).
     * @api
     */
    public function symbols(): SymbolIndex
    {
        return $this->symbolIndex;
    }
}
