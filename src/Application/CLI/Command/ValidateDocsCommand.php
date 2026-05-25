<?php

namespace FlowEngine\Application\CLI\Command;

use FlowEngine\Domain\Contracts\Flow;
use FlowEngine\Domain\Validation\DocumentationValidator;
use FlowEngine\Domain\Validation\DocumentationUpdater;
use FlowEngine\Domain\Validation\ValidationReport;

/**
 * Comando para validar documentação contra código real.
 * 
 * Verifica se arquivos e métodos mencionados na documentação existem,
 * e se as anotações (@api, @internal, @future) estão corretas.
 * 
 * Uso:
 *   php bin/engine.php validate-docs [arquivo.md]
 * 
 * @internal
 */
final class ValidateDocsCommand implements Command
{
    public function __construct(
        private Flow $flow,
        private ConsoleIO $io
    ) {}

    /**
     * @internal
     */
    public function supports(string $command): bool
    {
        return $command === 'validate-docs';
    }

    /**
     * @internal
     */
    public function handle(array $args): void
    {
        $docsFile = $args[0] ?? 'docs/maintainers/documentation-validation.md';

        if (!file_exists($docsFile)) {
            $this->io->error("Documentation file not found: {$docsFile}");
            $this->io->info("Usage: validate-docs [file.md]");
            return;
        }

        $this->io->info("📋 Validating {$docsFile} against codebase...\n");

        try {
            $validator = new DocumentationValidator(
                $this->flow,
                $docsFile,
                getcwd() // project root
            );

            $report = $validator->validate();

            $this->displayReport($report);

            if ($report->hasIssues()) {
                $this->handleIssues($report);
            } else {
                $this->io->success("\n✅ Documentation is up to date!");
            }
        } catch (\Exception $e) {
            $this->io->error("Validation failed: " . $e->getMessage());
        }
    }

    /**
     * Exibe relatório formatado.
     * 
     * @internal
     */
    private function displayReport(ValidationReport $report): void
    {
        $stats = $report->getStats();

        $this->io->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->io->info("📊 Summary:");
        $this->io->info("  Total references: {$stats['total']}");
        
        $validCount = $stats['total'] - $stats['issues'];
        $this->io->info("  ✓ Valid: {$validCount} (" . number_format($stats['successRate'], 1) . "%)");
        
        if ($stats['issues'] > 0) {
            $this->io->warning("  ✗ Issues: {$stats['issues']}");
        }
        
        $this->io->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");

        // Exibir issues por tipo
        if (!empty($stats['byType'])) {
            $this->displayIssuesByType($report, $stats['byType']);
        }
    }

    /**
     * Exibe issues agrupados por tipo.
     * 
     * @internal
     * @param array<string, int> $byType
     */
    private function displayIssuesByType(ValidationReport $report, array $byType): void
    {
        foreach ($byType as $type => $count) {
            $icon = $this->getIconForType($type);
            $this->io->warning("{$icon} {$type} ({$count}):");
            
            $issues = $report->getIssues($type);
            $displayed = array_slice($issues, 0, 5); // Mostrar no máximo 5
            
            foreach ($displayed as $issue) {
                $this->io->info("  • {$issue->file}" . 
                    ($issue->method ? "::{$issue->method}()" : ""));
            }
            
            if (count($issues) > 5) {
                $remaining = count($issues) - 5;
                $this->io->info("  ... and {$remaining} more");
            }
            
            $this->io->info("");
        }
    }

    /**
     * Retorna ícone apropriado para tipo de issue.
     * 
     * @internal
     */
    private function getIconForType(string $type): string
    {
        return match ($type) {
            'FILE_NOT_FOUND' => '❌',
            'METHOD_NOT_FOUND' => '⚠️',
            'ALREADY_DONE' => '✅',
            'CONFLICT' => 'ℹ️',
            default => '•'
        };
    }

    /**
     * Trata issues encontrados.
     * 
     * @internal
     */
    private function handleIssues(ValidationReport $report): void
    {
        $this->io->warning("\n💡 Recommendations:");
        
        $criticalCount = count($report->getCriticalIssues());
        
        if ($criticalCount > 0) {
            $this->io->info("  1. Fix {$criticalCount} critical issues (files/methods not found)");
        }
        
        $alreadyDone = count($report->getIssues('ALREADY_DONE'));
        if ($alreadyDone > 0) {
            $this->io->info("  2. Mark {$alreadyDone} items as completed");
        }
        
        $conflicts = count($report->getIssues('CONFLICT'));
        if ($conflicts > 0) {
            $this->io->info("  3. Resolve {$conflicts} annotation conflicts");
        }

        // Perguntar se quer gerar documentação atualizada
        if ($this->io->confirm("\nGenerate updated documentation?")) {
            $this->generateUpdatedDocs($report);
        }
    }

    /**
     * Gera documentação atualizada.
     * 
     * @internal
     */
    private function generateUpdatedDocs(ValidationReport $report): void
    {
        $this->io->info("\n🔧 Generating updated documentation...");
        
        try {
            $updater = new DocumentationUpdater();
            $updated = $updater->update($report);

            $outputFile = str_replace('.md', '_UPDATED.md', $report->docsFile);
            file_put_contents($outputFile, $updated);

            // Gerar resumo de mudanças
            $summary = $updater->generateChangeSummary($report);
            $summaryFile = str_replace('.md', '_SUMMARY.md', $report->docsFile);
            file_put_contents($summaryFile, $summary);

            $this->io->success("\n✓ Updated documentation saved to: {$outputFile}");
            $this->io->info("✓ Change summary saved to: {$summaryFile}");
            
            $this->io->info("\nNext steps:");
            $this->io->info("  1. Review changes: diff {$report->docsFile} {$outputFile}");
            $this->io->info("  2. Apply updates: mv {$outputFile} {$report->docsFile}");
        } catch (\Exception $e) {
            $this->io->error("Failed to generate updated docs: " . $e->getMessage());
        }
    }
}
