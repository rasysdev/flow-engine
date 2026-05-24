<?php

namespace FlowEngine\Infrastructure\Execution\Policy;

use FlowEngine\Domain\Execution\ExecutionContext;
use FlowEngine\Domain\Execution\ExecutionPolicy;
use FlowEngine\Domain\Execution\ExecutionPolicyResult;
use FlowEngine\Domain\Flow\Node;

/**
 * Composição de múltiplas policies com short-circuit no primeiro deny.
 *
 * Avalia policies em ordem. Se qualquer uma negar, retorna imediatamente.
 * Se todas permitirem, retorna allow.
 */
final class CompositeExecutionPolicy implements ExecutionPolicy
{
    /** @var ExecutionPolicy[] */
    private readonly array $policies;

    /**
     * @param ExecutionPolicy[] $policies Policies a serem avaliadas em ordem
     */
    public function __construct(array $policies)
    {
        $this->policies = $policies;
    }

    /**
     * Avalia todas as policies. First-deny short-circuit.
     *
     * @param Node             $node    Nó a ser executado
     * @param ExecutionContext  $context Contexto de execução
     *
     * @return ExecutionPolicyResult
     */
    public function canExecute(Node $node, ExecutionContext $context): ExecutionPolicyResult
    {
        foreach ($this->policies as $policy) {
            $result = $policy->canExecute($node, $context);

            if ($result->isDenied()) {
                return $result;
            }
        }

        return ExecutionPolicyResult::allow('All policies passed');
    }
}
