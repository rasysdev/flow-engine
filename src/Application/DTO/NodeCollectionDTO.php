<?php

namespace FlowEngine\Application\DTO;

/**
 * Coleção tipada e iterável de NodeDTOs.
 * 
 * Fornece métodos utilitários para trabalhar com listas de nodes.
 */
final readonly class NodeCollectionDTO implements \IteratorAggregate, \Countable
{
    /** @var NodeDTO[] */
    private array $nodes;

    /**
     * @param NodeDTO[] $nodes
     */
    public function __construct(array $nodes)
    {
        // Validar que todos são NodeDTO
        foreach ($nodes as $node) {
            if (!$node instanceof NodeDTO) {
                throw new \InvalidArgumentException(
                    'All items must be instances of NodeDTO'
                );
            }
        }

        $this->nodes = array_values($nodes); // Re-index
    }

    /**
     * Cria coleção a partir de entidades de Domain.
     * 
     * @param \FlowEngine\Domain\Flow\Node[] $nodes
     * @return self
     */
    public static function fromNodes(array $nodes): self
    {
        return new self(
            array_map(
                fn($node) => NodeDTO::fromNode($node),
                $nodes
            )
        );
    }

    /**
     * Retorna todos os DTOs.
     * 
     * @return NodeDTO[]
     */
    public function all(): array
    {
        return $this->nodes;
    }

    /**
     * Retorna primeiro DTO ou null.
     * 
     * @api
     */
    public function first(): ?NodeDTO
    {
        return $this->nodes[0] ?? null;
    }

    /**
     * Retorna último DTO ou null.
     * 
     * @api
     */
    public function last(): ?NodeDTO
    {
        return $this->nodes[array_key_last($this->nodes)] ?? null;
    }

    /**
     * Filtra nodes por callback.
     * 
     * @api
     * @param callable(NodeDTO): bool $callback
     * @return self
     */
    public function filter(callable $callback): self
    {
        return new self(array_filter($this->nodes, $callback));
    }

    /**
     * Mapeia nodes para novo array.
     * 
     * @api
     * @template T
     * @param callable(NodeDTO): T $callback
     * @return array<int, T>
     */
    public function map(callable $callback): array
    {
        return array_map($callback, $this->nodes);
    }

    /**
     * Verifica se coleção está vazia.
     */
    public function isEmpty(): bool
    {
        return empty($this->nodes);
    }

    /**
     * Conta DTOs na coleção.
     * 
     * @api
     */
    public function count(): int
    {
        return count($this->nodes);
    }

    /**
     * Permite iteração com foreach.
     * 
     * @return \Traversable<int, NodeDTO>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->nodes);
    }

    /**
     * Serializa toda coleção para array.
     * 
     * @api
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(
            fn(NodeDTO $node) => $node->toArray(),
            $this->nodes
        );
    }

    /**
     * Serializa para JSON.
     * 
     * @api
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }
}