<?php

namespace FlowEngine\Application\DTO;

final class NodeInputs
{
    /**
     * @param NodeInputDefinition[] $inputs
     */
    public function __construct(
        public array $inputs,
        public string $returnType
    ) {
    }
}
