<?php

namespace FlowEngine\Application\DTO;

/**
 * Resultado da resolução guiada de argumentos
 */
final class GuidedArgumentsResult
{
    /**
     * @param array<int, mixed> $args
     * @param ResolvedInputs[] $inputs
     */
    public function __construct(
        public array $args,
        public array $inputs
    ) {
    }
}
