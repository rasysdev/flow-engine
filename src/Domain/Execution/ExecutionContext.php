<?php

namespace FlowEngine\Domain\Execution;

final class ExecutionContext
{
    private function __construct(
        public readonly string $id,
        public readonly ExecutionOrigin $origin,
        public readonly ExecutionMode $mode,
        public readonly string $intent,
        public readonly float $startedAt
    ) {
    }

    public static function system(string $intent): self
    {
        return new self(
            id: uniqid('exec_', true),
            origin: ExecutionOrigin::SYSTEM,
            mode: ExecutionMode::AUTOMATIC,
            intent: $intent,
            startedAt: microtime(true)
        );
    }

    public static function guided(string $intent): self
    {
        return new self(
            id: uniqid('exec_', true),
            origin: ExecutionOrigin::USER,
            mode: ExecutionMode::GUIDED,
            intent: $intent,
            startedAt: microtime(true)
        );
    }

    /**
     * Factory de compatibilidade semântica:
     * execução direta de um Node
     */
    public static function forNode(string $nodeId): self
    {
        return self::system(
            intent: "execute node {$nodeId}"
        );
    }
}
