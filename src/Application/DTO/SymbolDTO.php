<?php

declare(strict_types=1);

namespace FlowEngine\Application\DTO;

/**
 * Represents a top-level symbol (import, export, function, const) found in a source file.
 *
 * @api
 */
final readonly class SymbolDTO
{
    /**
     * @param string      $id           Unique identifier: "{kind}:{file_path}::{name}"
     * @param string      $name         Symbol name (e.g. "TriangleAlertIcon", "lastError")
     * @param string      $kind         One of: import, export_function, export_const, function, const
     * @param string      $file         Absolute path to the source file
     * @param int         $line         1-based line number
     * @param string|null $sourceModule Import source module (e.g. "lucide-react"); null for non-imports
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $kind,
        public readonly string $file,
        public readonly int $line,
        public readonly ?string $sourceModule = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'kind'         => $this->kind,
            'file'         => $this->file,
            'line'         => $this->line,
            'sourceModule' => $this->sourceModule,
        ];
    }

    /**
     * Build a SymbolDTO and auto-generate its ID from kind + file + name.
     */
    public static function make(
        string $name,
        string $kind,
        string $file,
        int $line,
        ?string $sourceModule = null
    ): self {
        $id = $kind . ':' . $file . '::' . $name;

        return new self($id, $name, $kind, $file, $line, $sourceModule);
    }
}
