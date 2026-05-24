<?php

namespace FlowEngine\Domain\Flow\Query\Filters;

use FlowEngine\Domain\Flow\NodeCollection;
use FlowEngine\Domain\Flow\Query\FlowQueryFilter;

final class ClassPrefixFilter implements FlowQueryFilter
{
    public function __construct(
        private string $prefix
    ) {
    }

    /**
     * @internal 
     */
    public function apply(NodeCollection $nodes): NodeCollection
    {
        return $nodes->whereClassStartsWith($this->prefix);
    }

    /**
     * @internal 
     */
    public function type(): string
    {
        return 'class_prefix';
    }
    /**
     * @internal 
     */
    public function payload(): array
    {
        return [
            'prefix' => $this->prefix
        ];
    }
}
