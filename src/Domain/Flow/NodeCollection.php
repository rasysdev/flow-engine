<?php

namespace FlowEngine\Domain\Flow;

final class NodeCollection
{
    /** @var Node[] */
    private array $nodes;

    public function __construct(array $nodes)
    {
        $this->nodes = array_values($nodes);
    }

    /** @return Node[] */
    public function all(): array
    {
        return $this->nodes;
    }

    public function whereClassStartsWith(string $prefix): self
    {
        return new self(
            array_filter(
                $this->nodes,
                fn(Node $node) => str_starts_with($node->class(), $prefix)
            )
        );
    }

    public function whereMethod(string $method): self
    {
        return new self(
            array_filter(
                $this->nodes,
                fn(Node $node) => $node->method() === $method
            )
        );
    }

    public function onlyPublic(): self
    {
        return new self(
            array_filter(
                $this->nodes,
                fn(Node $node) => $node->isPublic()
            )
        );
    }

    /**
     * Alias semântico (retrocompatibilidade)
     */
    public function publicNodes(): self
    {
        return $this->onlyPublic();
    }

    public function onlyApplicationCode(): self
    {
        return new self(
            array_filter(
                $this->nodes,
                fn(Node $node) =>
                !str_starts_with($node->class(), 'Illuminate\\')
                && !str_starts_with($node->class(), 'Symfony\\')
            )
        );
    }

    public function count(): int
    {
        return count($this->nodes);
    }
}
