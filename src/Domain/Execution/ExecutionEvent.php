<?php

namespace FlowEngine\Domain\Execution;

use Throwable;

final class ExecutionEvent
{
    private function __construct(
        public readonly ExecutionEventType $type,
        public readonly string $nodeId,
        public readonly float $timestamp,
        public readonly float $durationMs,
        public readonly ?array $inputs,
        public readonly mixed $output,
        public readonly ?Throwable $exception,
        public readonly ?ExecutionContext $context
    ) {
    }

    public static function started(
        string $nodeId,
        array $inputs,
        ExecutionContext $context
    ): self {
        return new self(
            ExecutionEventType::STARTED,
            $nodeId,
            microtime(true),
            0.0,
            $inputs,
            null,
            null,
            $context
        );
    }

    public static function succeeded(
        string $nodeId,
        array $inputs,
        mixed $output,
        float $durationMs,
        ExecutionContext $context
    ): self {
        return new self(
            ExecutionEventType::SUCCEEDED,
            $nodeId,
            microtime(true),
            $durationMs,
            $inputs,
            $output,
            null,
            $context
        );
    }

    public static function failed(
        string $nodeId,
        array $inputs,
        Throwable $exception,
        float $durationMs,
        ExecutionContext $context
    ): self {
        return new self(
            ExecutionEventType::FAILED,
            $nodeId,
            microtime(true),
            $durationMs,
            $inputs,
            null,
            $exception,
            $context
        );
    }

    public function isFailure(): bool
    {
        return $this->type === ExecutionEventType::FAILED;
    }

    public function isSuccess(): bool
    {
        return $this->type === ExecutionEventType::SUCCEEDED;
    }

    /**
     * Reconstrói um evento a partir de dados persistidos.
     *
     * Preserva o timestamp original ao invés de gerar novo.
     * Usado para deserialização de event stores.
     *
     * @param ExecutionEventType  $type       Tipo do evento
     * @param string              $nodeId     Identificador do nó
     * @param float               $timestamp  Timestamp original
     * @param float               $durationMs Duração em milissegundos
     * @param array|null          $inputs     Inputs da execução
     * @param mixed               $output     Output da execução
     * @param Throwable|null      $exception  Exceção (se falha)
     * @param ExecutionContext|null $context   Contexto de execução
     *
     * @return self
     */
    public static function reconstruct(
        ExecutionEventType $type,
        string $nodeId,
        float $timestamp,
        float $durationMs,
        ?array $inputs,
        mixed $output,
        ?Throwable $exception,
        ?ExecutionContext $context
    ): self {
        return new self(
            $type,
            $nodeId,
            $timestamp,
            $durationMs,
            $inputs,
            $output,
            $exception,
            $context
        );
    }
}
