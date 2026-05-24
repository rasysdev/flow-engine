<?php

namespace FlowEngine\Domain\Flow;

use FlowEngine\Domain\Flow\Query\Filters\ApplicationCodeFilter;
use FlowEngine\Domain\Flow\Query\Filters\ClassPrefixFilter;
use FlowEngine\Domain\Flow\Query\Filters\VisibilityFilter;
use FlowEngine\Domain\Flow\Query\FlowQueryDefinition;
use FlowEngine\Domain\Node\NodeVisibility;
use FlowEngine\Domain\Flow\Query\Filters\MethodFilter;

final class FlowQuery
{
    /** @var object[] */
    private array $filters = [];

    private ?string $intent = null;

    public function __construct(
        private Flow $flow
    ) {
    }

    public function byClass(string $prefix): self
    {
        $clone = clone $this;
        $clone->filters[] = new ClassPrefixFilter($prefix);
        return $clone;
    }

    public function byMethod(string $method): self
    {
        $clone = clone $this;
        $clone->filters[] = new MethodFilter($method);
        return $clone;
    }

    public function publicNodes(): self
    {
        $clone = clone $this;
        $clone->filters[] = new VisibilityFilter(NodeVisibility::PUBLIC);
        return $clone;
    }

    public function onlyApplicationCode(): self
    {
        $clone = clone $this;
        $clone->filters[] = new ApplicationCodeFilter();
        return $clone;
    }

    public function describe(string $intent): self
    {
        $clone = clone $this;
        $clone->intent = $intent;
        return $clone;
    }

    /**
     * @return Node[]
     */
    public function all(): array
    {
        $nodes = new NodeCollection($this->flow->nodes());

        foreach ($this->filters as $filter) {
            $nodes = $filter->apply($nodes);
        }

        return $nodes->all();
    }

    public function first(): ?Node
    {
        $all = $this->all();
        return $all[0] ?? null;
    }

    public function toCollection(): NodeCollection
    {
        return new NodeCollection($this->all());
    }

    public function count(): int
    {
        return count($this->all());
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    public function byNamespace(string $namespace): self
    {
        $clone = clone $this;
        $clone->filters[] = new Query\Filters\NamespaceFilter($namespace);
        return $clone;
    }

    public function entrypoints(): self
    {
        $clone = clone $this;
        $clone->filters[] = new Query\Filters\EntrypointFilter($this->flow);
        return $clone;
    }

    public function leafNodes(): self
    {
        $clone = clone $this;
        $clone->filters[] = new Query\Filters\LeafNodeFilter($this->flow);
        return $clone;
    }

    public function excludeVendor(): self
    {
        return $this->onlyApplicationCode();
    }

    public function from(string $nodeId): FlowQueryTrace
    {
        return new FlowQueryTrace($this->flow, $nodeId);
    }

    public function toDefinition(): FlowQueryDefinition
    {
        $def = new FlowQueryDefinition();

        foreach ($this->filters as $filter) {
            $def->addFilter($filter);
        }

        if ($this->intent) {
            $def->describe($this->intent);
        }

        return $def;
    }
}