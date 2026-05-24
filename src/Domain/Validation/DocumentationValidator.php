<?php

namespace FlowEngine\Domain\Validation;

use FlowEngine\Domain\Contracts\Flow;

/**
 * Valida se a documentação está sincronizada com o código real.
 */
final class DocumentationValidator
{
    public function __construct(
        private Flow $flow,
        private string $docsPath,
        private string $projectRoot
    ) {}

    public function validate(): ValidationReport
    {
        $references = $this->parseDocumentation();
        $issues = [];
        $missingFiles = [];

        foreach ($references as $ref) {
            // Check file exists
            if (!$this->fileExists($ref['file'])) {
                // Only report FILE_NOT_FOUND once per file
                if (!in_array($ref['file'], $missingFiles)) {
                    $issues[] = Issue::fileNotFound($ref['file']);
                    $missingFiles[] = $ref['file'];
                }
                continue;
            }

            // Check method exists
            if (isset($ref['method']) && !$this->methodExists($ref['file'], $ref['method'])) {
                $issues[] = Issue::methodNotFound($ref['file'], $ref['method']);
                continue;
            }

            // Check annotation status
            if (isset($ref['expected'])) {
                $current = $this->getCurrentAnnotation($ref['file'], $ref['method']);

                if ($current === $ref['expected']) {
                    $issues[] = Issue::alreadyDone($ref['file'], $ref['method'], $current);
                } elseif ($current !== null) {
                    $issues[] = Issue::conflict($ref['file'], $ref['method'], $ref['expected'], $current);
                }
            }
        }

        return new ValidationReport(
            docsFile: $this->docsPath,
            issues: $issues,
            totalReferences: count($references)
        );
    }

    /**
     * Parse markdown e extrai referências de arquivos/métodos.
     * 
     * @return array<array{file: string, method?: string, expected?: string}>
     */
    private function parseDocumentation(): array
    {
        $content = file_get_contents($this->docsPath);
        $references = [];
        $currentFile = null;

        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);

            // Match file paths: src/Path/To/File.php
            if (preg_match('/(src\/[A-Za-z\/]+\.php)/', $line, $fileMatch)) {
                $currentFile = $fileMatch[1];
                $references[] = ['file' => $currentFile];
            }

            // Match method references: - @annotation methodName()
            if ($currentFile && preg_match('/^-\s+(@\w+)\s+(\w+)\(\)/', $line, $methodMatch)) {
                $references[] = [
                    'file' => $currentFile,
                    'method' => $methodMatch[2],
                    'expected' => $methodMatch[1],
                ];
            }
        }

        return $references;
    }

    private function fileExists(string $path): bool
    {
        return file_exists($this->projectRoot . '/' . $path);
    }

    private function methodExists(string $file, string $method): bool
    {
        foreach ($this->flow->nodes() as $node) {
            if (str_contains($node->file(), $file) && $node->method() === $method) {
                return true;
            }
        }

        return false;
    }

    private function getCurrentAnnotation(string $file, string $method): ?string
    {
        // Use reflection para ler docblock real
        // Por enquanto, retorna null (não implementado)
        return null;
    }
}