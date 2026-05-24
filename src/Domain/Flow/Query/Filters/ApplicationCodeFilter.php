<?php

namespace FlowEngine\Domain\Flow\Query\Filters;

use FlowEngine\Domain\Flow\NodeCollection;
use FlowEngine\Domain\Flow\Query\FlowQueryFilter;

final class ApplicationCodeFilter implements FlowQueryFilter
{
    /**
     * @internal 
     */
    public function apply(NodeCollection $nodes): NodeCollection
    {
        return $nodes->onlyApplicationCode();
    }

    /**
     * @internal 
     */
    public function type(): string
    {
        return 'application_code';
    }

    /**
     * @internal 
     */
    public function payload(): array
    {
        return [];
    }
}
