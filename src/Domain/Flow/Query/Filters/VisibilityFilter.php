<?php

namespace FlowEngine\Domain\Flow\Query\Filters;

use FlowEngine\Domain\Flow\NodeCollection;
use FlowEngine\Domain\Flow\Query\FlowQueryFilter;
use FlowEngine\Domain\Node\NodeVisibility;

final class VisibilityFilter implements FlowQueryFilter
{
    public function __construct(
        private string $visibility
    ) {
    }

    /**
     * @internal 
     */
    public function apply(NodeCollection $nodes): NodeCollection
    {
        if ($this->visibility === NodeVisibility::PUBLIC) {
            return $nodes->onlyPublic();
        }

        return $nodes;
    }

    /**
     * @internal 
     */
    public function type(): string
    {
        return 'visibility';
    }

    /**
     * @internal 
     */
    public function payload(): array
    {
        return ['visibility' => $this->visibility];
    }
}
