<?php

namespace FlowEngine\Infrastructure\Config;

use RuntimeException;

final class SchemaValidator
{
    public function __construct(
        private string $schemaPath
    ) {
        if (!file_exists($schemaPath)) {
            throw new RuntimeException('Schema file not found');
        }
    }

    /** 
     * @internal
     * 
     */
    public function validate(array $data): void
    {
        // Versão v1: validações estruturais mínimas
        if (($data['version'] ?? null) !== '1.0') {
            throw new RuntimeException('Unsupported config version');
        }

        if (!isset($data['context']['type'])) {
            throw new RuntimeException('context.type is required');
        }

        if (!isset($data['scan']['include'])) {
            throw new RuntimeException('scan.include is required');
        }
    }
}
