<?php

namespace FlowEngine\Domain\Flow\Query;

use FlowEngine\Domain\Flow\NodeCollection;

interface FlowQueryFilter
{
    public function apply(NodeCollection $nodes): NodeCollection;

    /**
     * Identificador semântico do filtro
     * (ex: class_prefix, visibility, application_code)
     */
    public function type(): string;

    /**
     * Payload serializável do filtro
     */
    public function payload(): array;
}
