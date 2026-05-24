<?php

namespace FlowEngine\Domain\Flow\Query\Filters;

use FlowEngine\Domain\Flow\Flow;
use FlowEngine\Domain\Flow\NodeCollection;

/**
 * Filtra apenas entry points (nodes sem upstream).
 * 
 * Entry point = método que não é chamado por ninguém no Flow.
 * Geralmente são: controllers, commands, event handlers, cron jobs.
 */
final class EntrypointFilter
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
        // Coletar todos os targets de edges (nodes que SÃO chamados)
        $calledNodes = [];
        foreach ($this->flow->edges() as $edge) {
            $calledNodes[$edge->to()] = true;
        }

        // Filtrar nodes que NÃO aparecem como targets
        $filtered = [];
        foreach ($nodes->all() as $node) {
            if (!isset($calledNodes[$node->id()])) {
                $filtered[] = $node;
            }
        }

        return new NodeCollection($filtered);
    }
}