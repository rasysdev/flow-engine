<?php

namespace FlowEngine\Domain\Validation;

/**
 * Representa um problema encontrado na validação da documentação.
 */
final class Issue
{
    public function __construct(
        public readonly string $type,        // FILE_NOT_FOUND, METHOD_NOT_FOUND, etc.
        public readonly string $file,        // Arquivo afetado
        public readonly ?string $method,     // Método afetado (se aplicável)
        public readonly ?string $expected,   // Anotação esperada
        public readonly ?string $current,    // Anotação atual
        public readonly string $message      // Mensagem descritiva
    ) {
    }

    public function isType(string $type): bool
    {
        return $this->type === $type;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'file' => $this->file,
            'method' => $this->method,
            'expected' => $this->expected,
            'current' => $this->current,
            'message' => $this->message,
        ];
    }
    public static function fileNotFound(string $file): self
    {
        return new self(
            type: 'FILE_NOT_FOUND',
            file: $file,
            method: null,
            expected: null,
            current: null,
            message: "File not found: {$file}"
        );
    }

    public static function methodNotFound(string $file, string $method): self
    {
        return new self(
            type: 'METHOD_NOT_FOUND',
            file: $file,
            method: $method,
            expected: null,
            current: null,
            message: "Method {$method} not found in {$file}"
        );
    }

    public static function alreadyDone(string $file, string $method, string $annotation): self
    {
        return new self(
            type: 'ALREADY_DONE',
            file: $file,
            method: $method,
            expected: $annotation,
            current: $annotation,
            message: "Method {$method} already has {$annotation} annotation"
        );
    }

    public static function conflict(string $file, string $method, string $expected, string $current): self
    {
        return new self(
            type: 'CONFLICT',
            file: $file,
            method: $method,
            expected: $expected,
            current: $current,
            message: "Method {$method} has {$current} but expected {$expected}"
        );
    }
}