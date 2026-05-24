<?php

namespace FlowEngine\Domain\Execution;

/**
 * Resultado de avaliação de uma ExecutionPolicy.
 *
 * Value object imutável com factory methods allow/deny.
 */
final class ExecutionPolicyResult
{
    private function __construct(
        public readonly bool $allowed,
        public readonly string $reason
    ) {
    }

    /**
     * Cria resultado permitindo execução.
     *
     * @param string $reason Motivo da permissão
     *
     * @return self
     */
    public static function allow(string $reason = 'Allowed by policy'): self
    {
        return new self(allowed: true, reason: $reason);
    }

    /**
     * Cria resultado negando execução.
     *
     * @param string $reason Motivo da negação
     *
     * @return self
     */
    public static function deny(string $reason): self
    {
        return new self(allowed: false, reason: $reason);
    }

    /**
     * Verifica se a execução foi permitida.
     *
     * @return bool
     */
    public function isAllowed(): bool
    {
        return $this->allowed;
    }

    /**
     * Verifica se a execução foi negada.
     *
     * @return bool
     */
    public function isDenied(): bool
    {
        return !$this->allowed;
    }
}
