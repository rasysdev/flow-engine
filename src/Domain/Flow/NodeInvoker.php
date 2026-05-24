<?php

namespace FlowEngine\Domain\Flow;

interface NodeInvoker
{
    /**
     * Executa um Node já resolvido e retorna o resultado bruto
     *
     * @param Node $node
     * @param array<int, mixed> $args
     */
    public function invoke(Node $node, array $args): mixed;
}
