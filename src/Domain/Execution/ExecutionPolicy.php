<?php

namespace FlowEngine\Domain\Execution;

use FlowEngine\Domain\Flow\Node;

/**
 * Contrato para políticas de execução.
 *
 * Cada policy decide se um nó pode ser executado dado o contexto atual.
 * Policies são avaliadas antes do início da execução.
 */
interface ExecutionPolicy
{
    /**
     * Avalia se o nó pode ser executado no contexto dado.
     *
     * @param Node             $node    Nó a ser executado
     * @param ExecutionContext  $context Contexto de execução atual
     *
     * @return ExecutionPolicyResult Resultado com allow/deny e motivo
     */
    public function canExecute(Node $node, ExecutionContext $context): ExecutionPolicyResult;
}
