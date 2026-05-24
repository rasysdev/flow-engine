<?php

namespace FlowEngine\Domain\Flow\Query\Filters;

use FlowEngine\Domain\Flow\Flow;
use FlowEngine\Domain\Flow\NodeCollection;

/**
 * Filtra apenas leaf nodes (nodes sem downstream).
 * 
 * Leaf node = método que não chama ninguém no Flow.
 * Geralmente são: utilities, getters, helpers simples.
 */
final class LeafNodeFilter
{
    public function __construct(
        private Flow $flow
    ) {
    }

    /**
     * @internal 
     */
    public function apply(NodeCollection $nodes): NodeCollection
    {
        // Coletar todos os sources de edges (nodes que FAZEM chamadas)
        $callingNodes = [];
        foreach ($this->flow->edges() as $edge) {
            $callingNodes[$edge->from()] = true;
        }

        // Filtrar nodes que NÃO aparecem como sources
        $filtered = [];
        foreach ($nodes->all() as $node) {
            if (!isset($callingNodes[$node->id()])) {
                $filtered[] = $node;
            }
        }

        return new NodeCollection($filtered);
    }
}