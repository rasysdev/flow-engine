<?php

namespace FlowEngine\Domain\Validation;

/**
 * Atualiza documentação baseado no relatório de validação.
 * 
 * Service que processa um ValidationReport e gera versão corrigida
 * da documentação, removendo referências inválidas e marcando
 * items já completados.
 * 
 * @internal
 */
final class DocumentationUpdater
{
    /**
     * Atualiza documentação baseado no relatório.
     * 
     * @internal
     * @param ValidationReport $report Relatório de validação
     * @return string Conteúdo atualizado da documentação
     * @throws \RuntimeException Se não conseguir ler arquivo
     */
    public function update(ValidationReport $report): string
    {
        if (!is_file($report->docsFile) || !is_readable($report->docsFile)) {
            throw new \RuntimeException("Failed to read {$report->docsFile}");
        }

        $content = file_get_contents($report->docsFile);

        if ($content === false) {
            throw new \RuntimeException("Failed to read {$report->docsFile}");
        }

        // Processar cada tipo de issue
        $content = $this->removeFileNotFound($content, $report);
        $content = $this->removeMethodNotFound($content, $report);
        $content = $this->markAlreadyDone($content, $report);
        $content = $this->addConflictWarnings($content, $report);

        return $content;
    }

    /**
     * Remove seções de arquivos não encontrados.
     * 
     * @internal
     */
    private function removeFileNotFound(string $content, ValidationReport $report): string
    {
        foreach ($report->getIssues('FILE_NOT_FOUND') as $issue) {
            $content = $this->removeFileSection($content, $issue->file);
        }

        return $content;
    }

    /**
     * Remove referências a métodos não encontrados.
     * 
     * @internal
     */
    private function removeMethodNotFound(string $content, ValidationReport $report): string
    {
        foreach ($report->getIssues('METHOD_NOT_FOUND') as $issue) {
            if ($issue->method) {
                $content = $this->removeMethodReference($content, $issue->method);
            }
        }

        return $content;
    }

    /**
     * Marca items já completados.
     * 
     * @internal
     */
    private function markAlreadyDone(string $content, ValidationReport $report): string
    {
        foreach ($report->getIssues('ALREADY_DONE') as $issue) {
            if ($issue->method && $issue->expected) {
                $content = $this->markAsComplete($content, $issue->method, $issue->expected);
            }
        }

        return $content;
    }

    /**
     * Adiciona avisos sobre conflitos.
     * 
     * @internal
     */
    private function addConflictWarnings(string $content, ValidationReport $report): string
    {
        $warnings = [];

        foreach ($report->getIssues('CONFLICT') as $issue) {
            $warnings[] = $this->formatConflictWarning($issue);
        }

        if (!empty($warnings)) {
            $content .= "\n\n---\n\n## ⚠️ Annotation Conflicts\n\n";
            $content .= implode("\n\n", $warnings);
        }

        return $content;
    }

    /**
     * Remove seção de arquivo específico.
     * 
     * Removes the file path line and any subsequent method reference lines
     * (lines starting with - or *) until the next file reference, section
     * header, or non-method content is found.
     * 
     * @internal
     */
    private function removeFileSection(string $content, string $file): string
    {
        $lines = explode("\n", $content);
        $filtered = [];
        $skipping = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // If we find the target file, start skipping
            if (str_contains($line, $file)) {
                $skipping = true;
                continue;
            }

            // While skipping, remove method reference lines (- @... or * @...)
            if ($skipping) {
                // Stop skipping when we hit a non-method line
                // (another file ref, header, blank content that isn't just whitespace, etc.)
                if ($trimmed === '' || preg_match('/^[-*]\s+@/', $trimmed)) {
                    // Skip blank lines and method references within the section
                    continue;
                }
                // Found something else — stop skipping
                $skipping = false;
            }

            $filtered[] = $line;
        }

        return implode("\n", $filtered);
    }

    /**
     * Remove referência a método específico.
     * 
     * @internal
     */
    private function removeMethodReference(string $content, string $method): string
    {
        // Remove linhas que mencionam o método
        $pattern = '/^.*' . preg_quote($method, '/') . '\(\).*$/m';
        return preg_replace($pattern, '', $content) ?? $content;
    }

    /**
     * Marca método como completo.
     * 
     * @internal
     */
    private function markAsComplete(string $content, string $method, string $annotation): string
    {
        // Padrões comuns de documentação
        $patterns = [
            "- {$annotation} {$method}()",
            "* {$annotation} {$method}()",
            "{$annotation} {$method}()",
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($content, $pattern)) {
                $content = str_replace($pattern, $pattern . ' ✓', $content);
            }
        }

        return $content;
    }

    /**
     * Formata aviso de conflito.
     * 
     * @internal
     */
    private function formatConflictWarning(Issue $issue): string
    {
        $warning = "### {$issue->file}";
        
        if ($issue->method) {
            $warning .= "::{$issue->method}()";
        }
        
        $warning .= "\n\n";
        $warning .= "- **Expected:** `{$issue->expected}`\n";
        $warning .= "- **Current:** `{$issue->current}`\n";
        $warning .= "- **Action:** Review and update documentation or code annotation";

        return $warning;
    }

    /**
     * Gera resumo das mudanças aplicadas.
     * 
     * @internal
     */
    public function generateChangeSummary(ValidationReport $report): string
    {
        $summary = "# Documentation Update Summary\n\n";
        
        $stats = $report->getStats();
        
        $summary .= "## Changes Applied\n\n";
        
        foreach ($stats['byType'] as $type => $count) {
            $summary .= "- **{$type}:** {$count} items\n";
        }
        
        $summary .= "\n## Success Rate\n\n";
        $summary .= number_format($stats['successRate'], 1) . "% of references are valid\n";

        return $summary;
    }
}
