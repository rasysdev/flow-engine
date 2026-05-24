<?php

namespace FlowEngine\Infrastructure\Execution\Policy;

use FlowEngine\Domain\Execution\ExecutionContext;
use FlowEngine\Domain\Execution\ExecutionPolicy;
use FlowEngine\Domain\Execution\ExecutionPolicyResult;
use FlowEngine\Domain\Flow\Node;

/**
 * Policy de rate limiting por nó.
 *
 * Limita o número de execuções por nó dentro de uma janela de tempo.
 * Tracking em memória via array de timestamps.
 */
final class RateLimitExecutionPolicy implements ExecutionPolicy
{
    /** @var array<string, float[]> Timestamps de execução por nodeId */
    private array $executions = [];

    /**
     * @param int   $maxExecutions Número máximo de execuções na janela
     * @param float $windowSeconds Tamanho da janela em segundos
     */
    public function __construct(
        private readonly int $maxExecutions,
        private readonly float $windowSeconds
    ) {
    }

    /**
     * Avalia se o nó pode ser executado (dentro do rate limit).
     *
     * @param Node             $node    Nó a ser executado
     * @param ExecutionContext  $context Contexto de execução
     *
     * @return ExecutionPolicyResult
     */
    public function canExecute(Node $node, ExecutionContext $context): ExecutionPolicyResult
    {
        $nodeId = $node->id();
        $now = microtime(true);
        $windowStart = $now - $this->windowSeconds;

        // Limpa entradas fora da janela
        if (isset($this->executions[$nodeId])) {
            $this->executions[$nodeId] = array_values(
                array_filter(
                    $this->executions[$nodeId],
                    fn(float $ts) => $ts >= $windowStart
                )
            );
        } else {
            $this->executions[$nodeId] = [];
        }

        $count = count($this->executions[$nodeId]);

        if ($count >= $this->maxExecutions) {
            return ExecutionPolicyResult::deny(
                "Rate limit exceeded for '{$nodeId}': {$count}/{$this->maxExecutions} executions in {$this->windowSeconds}s window"
            );
        }

        // Registra a execução
        $this->executions[$nodeId][] = $now;

        return ExecutionPolicyResult::allow("Rate limit OK: " . ($count + 1) . "/{$this->maxExecutions}");
    }
}
