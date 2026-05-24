<?php

namespace FlowEngine\Application\DTO;

/**
 * Resultado da inspeção de inputs de um Node
 */
final class NodeInputsResult
{
    /**
     * @param NodeInputs[] $inputs
     */
    public function __construct(
        public array $inputs,
        public string $returnType
    ) {
    }
}
