<?php

namespace Tests\Support;

use FlowEngine\Domain\Contracts\FlowRepository;
use FlowEngine\Domain\Contracts\Flow as FlowContract;
use FlowEngine\Domain\Flow\Edge;
use FlowEngine\Domain\Flow\Flow;
use FlowEngine\Domain\Flow\Node;

final class InMemoryFlowRepository implements FlowRepository
{
    /** @var Node[] */
    private array $nodes;

    /** @var Edge[] */
    private array $edges;

    /**
     * @param Node[] $nodes
     * @param Edge[] $edges
     */
    public function __construct(array $nodes = [], array $edges = [])
    {
        $this->nodes = $nodes;
        $this->edges = $edges;
    }

    /**
     * @internal
     * @return void
     */
    public function analyze(): void
    {
        // noop — tudo já está em memória
    }

    /**
     * @internal
     * @return Node[]
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    /**
     * @internal
     * @return Node[]
     */

    public function getNode(string $id): Node
    {
        foreach ($this->nodes as $node) {
            if ($node->id() === $id) {
                return $node;
            }
        }

        throw new \LogicException("Node {$id} not found");
    }

    public function findNode(string $id): ?Node
    {
        foreach ($this->nodes as $node) {
            if ($node->id() === $id) {
                return $node;
            }
        }

        return null;
    }

    /**
     * @internal
     */
    public function getFlow(): FlowContract
    {
        return new Flow(
            nodes: $this->nodes,
            edges: $this->edges
        );
    }
}
