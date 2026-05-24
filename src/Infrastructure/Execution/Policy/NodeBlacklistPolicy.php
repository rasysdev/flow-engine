<?php

namespace FlowEngine\Infrastructure\Execution\Policy;

use FlowEngine\Domain\Execution\ExecutionContext;
use FlowEngine\Domain\Execution\ExecutionPolicy;
use FlowEngine\Domain\Execution\ExecutionPolicyResult;
use FlowEngine\Domain\Flow\Node;

/**
 * Policy de blacklist de nós.
 *
 * Nega execução para nodeIds na blacklist.
 */
final class NodeBlacklistPolicy implements ExecutionPolicy
{
    /** @var array<string, true> Lookup rápido por nodeId */
    private readonly array $blacklist;

    /**
     * @param string[] $blacklistedNodeIds Lista de nodeIds bloqueados
     */
    public function __construct(array $blacklistedNodeIds)
    {
        $this->blacklist = array_flip(array_map('strval', $blacklistedNodeIds));
    }

    /**
     * Avalia se o nó está na blacklist.
     *
     * @param Node             $node    Nó a ser executado
     * @param ExecutionContext  $context Contexto de execução
     *
     * @return ExecutionPolicyResult
     */
    public function canExecute(Node $node, ExecutionContext $context): ExecutionPolicyResult
    {
        $nodeId = $node->id();

        if (isset($this->blacklist[$nodeId])) {
            return ExecutionPolicyResult::deny(
                "Node '{$nodeId}' is blacklisted"
            );
        }

        return ExecutionPolicyResult::allow("Node not in blacklist");
    }
}
