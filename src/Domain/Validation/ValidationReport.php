<?php

namespace FlowEngine\Domain\Validation;

/**
 * Relatório de validação da documentação.
 * 
 * Aggregate que consolida todos os issues encontrados durante
 * a validação e fornece estatísticas sobre a qualidade da documentação.
 * 
 * @internal
 */
final class ValidationReport
{
    /**
     * @param string $docsFile Caminho do arquivo de documentação validado
     * @param Issue[] $issues Lista de problemas encontrados
     * @param int $totalReferences Total de referências verificadas
     */
    public function __construct(
        public readonly string $docsFile,
        public readonly array $issues,
        public readonly int $totalReferences
    ) {}

    /**
     * Verifica se há problemas no relatório.
     * 
     * @internal
     */
    public function hasIssues(): bool
    {
        return count($this->issues) > 0;
    }

    /**
     * Retorna quantidade de issues.
     * 
     * @internal
     */
    public function issueCount(): int
    {
        return count($this->issues);
    }

    /**
     * Retorna issues de um tipo específico.
     * 
     * @internal
     * @param string $type Tipo de issue (FILE_NOT_FOUND, METHOD_NOT_FOUND, etc)
     * @return Issue[]
     */
    public function getIssues(string $type): array
    {
        return array_values(array_filter(
            $this->issues,
            fn(Issue $issue) => $issue->type === $type
        ));
    }

    /**
     * Retorna estatísticas do relatório.
     * 
     * @internal
     * @return array{total: int, issues: int, byType: array<string, int>, successRate: float}
     */
    public function getStats(): array
    {
        $byType = [];

        foreach ($this->issues as $issue) {
            $byType[$issue->type] = ($byType[$issue->type] ?? 0) + 1;
        }

        return [
            'total' => $this->totalReferences,
            'issues' => count($this->issues),
            'byType' => $byType,
            'successRate' => $this->totalReferences > 0
                ? (($this->totalReferences - count($this->issues)) / $this->totalReferences) * 100
                : 100.0
        ];
    }

    /**
     * Verifica se a validação foi bem-sucedida (sem issues críticos).
     * 
     * @internal
     */
    public function isValid(): bool
    {
        // Considera válido se não há FILE_NOT_FOUND ou METHOD_NOT_FOUND
        $criticalTypes = ['FILE_NOT_FOUND', 'METHOD_NOT_FOUND'];

        foreach ($this->issues as $issue) {
            if (in_array($issue->type, $criticalTypes)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Retorna apenas issues críticos.
     * 
     * @internal
     * @return Issue[]
     */
    public function getCriticalIssues(): array
    {
        return array_values(array_filter(
            $this->issues,
            fn(Issue $issue) => in_array($issue->type, ['FILE_NOT_FOUND', 'METHOD_NOT_FOUND'])
        ));
    }

    /**
     * Converte para array.
     * 
     * @internal
     */
    public function toArray(): array
    {
        return [
            'docsFile' => $this->docsFile,
            'totalReferences' => $this->totalReferences,
            'issueCount' => $this->issueCount(),
            'issues' => array_map(fn($i) => $i->toArray(), $this->issues),
            'stats' => $this->getStats(),
        ];
    }
}