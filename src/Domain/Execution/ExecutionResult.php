<?php

namespace FlowEngine\Domain\Execution;

use Throwable;

final class ExecutionResult
{
    private function __construct(
        public readonly ExecutionContext $context,
        public readonly string $nodeId,
        public readonly array $inputs,
        public readonly mixed $output,
        public readonly ?Throwable $exception,
        public readonly float $durationMs
    ) {
    }

    public static function success(
        ExecutionContext $context,
        string $nodeId,
        array $inputs,
        mixed $output,
        float $durationMs
    ): self {
        return new self(
            context: $context,
            nodeId: $nodeId,
            inputs: $inputs,
            output: $output,
            exception: null,
            durationMs: $durationMs
        );
    }

    public static function failure(
        ExecutionContext $context,
        string $nodeId,
        array $inputs,
        Throwable $exception,
        float $durationMs
    ): self {
        return new self(
            context: $context,
            nodeId: $nodeId,
            inputs: $inputs,
            output: null,
            exception: $exception,
            durationMs: $durationMs
        );
    }

    public function isSuccess(): bool
    {
        return $this->exception === null;
    }

    public function isFailure(): bool
    {
        return $this->exception !== null;
    }
}
