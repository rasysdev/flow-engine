<?php

namespace FlowEngine\Application\DTO;

use FlowEngine\Domain\Execution\ExecutionResult;

/**
 * Resultado final da execução guiada de um Node
 */
final class RunNodeGuidedResult
{

    public function __construct(
        public string $nodeId,
        public array $inputs,
        public ExecutionResult $result
    ) {
    }
}
