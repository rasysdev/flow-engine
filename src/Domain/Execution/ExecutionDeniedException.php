<?php

namespace FlowEngine\Domain\Execution;

use RuntimeException;

/**
 * Exceção lançada quando uma policy nega a execução de um nó.
 */
final class ExecutionDeniedException extends RuntimeException
{
    public function __construct(
        public readonly string $nodeId,
        public readonly ExecutionPolicyResult $policyResult
    ) {
        parent::__construct(
            "Execution denied for node '{$nodeId}': {$policyResult->reason}"
        );
    }
}
