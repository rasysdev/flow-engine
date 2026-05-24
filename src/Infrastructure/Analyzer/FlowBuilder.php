<?php

namespace FlowEngine\Infrastructure\Analyzer;

use FlowEngine\Application\Policy\NodeVisibilityPolicy;
use FlowEngine\Domain\Flow\Flow;
use FlowEngine\Domain\Flow\Node;
use FlowEngine\Domain\Flow\SymbolIndex;

/**
 * Constrói o Flow aplicando policies de visibilidade.
 *
 * IDs duplicados são deduplicados silenciosamente (mantém última ocorrência).
 * A preferência pela última ocorrência alinha com TypeScript overloads, onde
 * o parser emite as assinaturas antes da implementação — manter a última
 * significa conservar a linha do corpo real. Preservar ordem de inserção
 * para os demais nós.
 *
 * A lista dos IDs duplicados encontrados fica disponível via lastDuplicateIds()
 * para que camadas superiores (repository/MCP) possam emitir warning.
 */
final class FlowBuilder
{
    /** @var string[] */
    private array $lastDuplicateIds = [];

    public function __construct(
        private NodeVisibilityPolicy $visibilityPolicy
    ) {
    }

    /**
     * @api
     * @param Node[] $nodes
     * @param \FlowEngine\Domain\Flow\Edge[] $edges
     */
    public function build(array $nodes, array $edges, ?SymbolIndex $symbols = null): Flow
    {
        $nodes = array_map(
            fn(Node $node) => $node->withVisibility(
                $this->visibilityPolicy->visibility($node)
            ),
            $nodes
        );

        foreach ($nodes as $node) {
            $node->validate();
        }

        $deduped = $this->dedupeById($nodes);

        return new Flow($deduped, $edges, $symbols);
    }

    /**
     * IDs duplicados detectados na última chamada a build().
     * Cada ID aparece uma única vez, ordenado alfabeticamente.
     *
     * @api
     * @return string[]
     */
    public function lastDuplicateIds(): array
    {
        return $this->lastDuplicateIds;
    }

    public function visibilityPolicy(): NodeVisibilityPolicy
    {
        return $this->visibilityPolicy;
    }

    /**
     * @param Node[] $nodes
     * @return Node[]
     */
    private function dedupeById(array $nodes): array
    {
        $positions = [];
        $duplicates = [];
        $unique = [];

        foreach ($nodes as $node) {
            $id = $node->id();
            if (isset($positions[$id])) {
                $duplicates[$id] = true;
                $unique[$positions[$id]] = $node;
                continue;
            }
            $positions[$id] = count($unique);
            $unique[] = $node;
        }

        $ids = array_keys($duplicates);
        sort($ids);
        $this->lastDuplicateIds = $ids;

        return array_values($unique);
    }
}
