<?php

namespace FlowEngine\Domain\Flow\Query\Filters;

use FlowEngine\Domain\Flow\NodeCollection;
use FlowEngine\Domain\Flow\Query\FlowQueryFilter;

final class MethodFilter implements FlowQueryFilter
{
    public function __construct(
        private string $method
    ) {
    }

    /**
     * @internal 
     */
    public function apply(NodeCollection $nodes): NodeCollection
    {
        return $nodes->whereMethod($this->method);
    }

    /**
     * @internal 
     */
    public function type(): string
    {
        return 'method';
    }

    /**
     * @internal 
     */
    public function payload(): array
    {
        return ['method' => $this->method];
    }
}
