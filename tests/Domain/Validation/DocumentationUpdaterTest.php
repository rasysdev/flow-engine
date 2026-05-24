<?php

namespace FlowEngine\Tests\Domain\Validation;

use FlowEngine\Domain\Validation\DocumentationUpdater;
use FlowEngine\Domain\Validation\Issue;
use FlowEngine\Domain\Validation\ValidationReport;
use PHPUnit\Framework\TestCase;

/**
 * Testes para DocumentationUpdater service.
 */
final class DocumentationUpdaterTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = sys_get_temp_dir() . '/test_docs_' . uniqid() . '.md';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testUpdateRemovesFileNotFoundReferences(): void
    {
        $content = <<<MD
        # Documentation
        
        src/Existing/File.php
        - @api method()
        
        src/Missing/File.php
        - @api missingMethod()
        MD;

        file_put_contents($this->tempFile, $content);

        $issues = [
            Issue::fileNotFound('src/Missing/File.php'),
        ];

        $report = new ValidationReport($this->tempFile, $issues, 2);
        $updater = new DocumentationUpdater();

        $updated = $updater->update($report);

        $this->assertStringNotContainsString('src/Missing/File.php', $updated);
        $this->assertStringContainsString('src/Existing/File.php', $updated);
    }

    public function testUpdateRemovesMethodNotFoundReferences(): void
    {
        $content = <<<MD
        # Documentation
        
        - @api existingMethod()
        - @api missingMethod()
        MD;

        file_put_contents($this->tempFile, $content);

        $issues = [
            Issue::methodNotFound('test.php', 'missingMethod'),
        ];

        $report = new ValidationReport($this->tempFile, $issues, 2);
        $updater = new DocumentationUpdater();

        $updated = $updater->update($report);

        $this->assertStringNotContainsString('missingMethod', $updated);
        $this->assertStringContainsString('existingMethod', $updated);
    }

    public function testUpdateMarksAlreadyDoneItems(): void
    {
        $content = <<<MD
        # Documentation
        
        - @api create()
        - @internal helper()
        MD;

        file_put_contents($this->tempFile, $content);

        $issues = [
            Issue::alreadyDone('test.php', 'create', '@api'),
        ];

        $report = new ValidationReport($this->tempFile, $issues, 2);
        $updater = new DocumentationUpdater();

        $updated = $updater->update($report);

        $this->assertStringContainsString('create() ✓', $updated);
        $this->assertStringNotContainsString('helper() ✓', $updated);
    }

    public function testUpdateAddsConflictWarnings(): void
    {
        $content = "# Documentation\n\n- @api method()";

        file_put_contents($this->tempFile, $content);

        $issues = [
            Issue::conflict('src/Test.php', 'method', '@api', '@internal'),
        ];

        $report = new ValidationReport($this->tempFile, $issues, 1);
        $updater = new DocumentationUpdater();

        $updated = $updater->update($report);

        $this->assertStringContainsString('Annotation Conflicts', $updated);
        $this->assertStringContainsString('src/Test.php::method()', $updated);
        $this->assertStringContainsString('`@api`', $updated); // Backticks na string
        $this->assertStringContainsString('`@internal`', $updated); // Backticks na string
    }

    public function testUpdateHandlesMultipleIssueTypes(): void
    {
        $content = <<<MD
        # Documentation
        
        src/Missing.php
        - @api missingMethod()
        
        src/Existing.php
        - @api doneMethod()
        - @api conflictMethod()
        MD;

        file_put_contents($this->tempFile, $content);

        $issues = [
            Issue::fileNotFound('src/Missing.php'),
            Issue::methodNotFound('src/Existing.php', 'missingMethod'),
            Issue::alreadyDone('src/Existing.php', 'doneMethod', '@api'),
            Issue::conflict('src/Existing.php', 'conflictMethod', '@api', '@internal'),
        ];

        $report = new ValidationReport($this->tempFile, $issues, 4);
        $updater = new DocumentationUpdater();

        $updated = $updater->update($report);

        // Arquivo não encontrado foi removido
        $this->assertStringNotContainsString('src/Missing.php', $updated);
        
        // Método não encontrado foi removido
        $this->assertStringNotContainsString('missingMethod', $updated);
        
        // Método done foi marcado
        $this->assertStringContainsString('doneMethod', $updated);
        $this->assertStringContainsString('✓', $updated);
        
        // Conflito foi adicionado à seção
        $this->assertStringContainsString('Annotation Conflicts', $updated);
        $this->assertStringContainsString('conflictMethod', $updated);
    }

    public function testUpdateThrowsExceptionForInvalidFile(): void
    {
        $report = new ValidationReport('/nonexistent/file.md', [], 0);
        $updater = new DocumentationUpdater();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to read');

        $updater->update($report);
    }

    public function testGenerateChangeSummaryIncludesStats(): void
    {
        $issues = [
            Issue::fileNotFound('test1.php'),
            Issue::methodNotFound('test2.php', 'method'),
            Issue::alreadyDone('test3.php', 'method', '@api'),
        ];

        $report = new ValidationReport('test.md', $issues, 10);
        $updater = new DocumentationUpdater();

        $summary = $updater->generateChangeSummary($report);

        $this->assertStringContainsString('Documentation Update Summary', $summary);
        $this->assertStringContainsString('FILE_NOT_FOUND', $summary);
        $this->assertStringContainsString('METHOD_NOT_FOUND', $summary);
        $this->assertStringContainsString('ALREADY_DONE', $summary);
        $this->assertStringContainsString('Success Rate', $summary);
        $this->assertStringContainsString('70.0%', $summary); // 7 ok de 10
    }

    public function testUpdateHandlesEmptyReport(): void
    {
        $content = "# Documentation\n\nAll good!";
        file_put_contents($this->tempFile, $content);

        $report = new ValidationReport($this->tempFile, [], 5);
        $updater = new DocumentationUpdater();

        $updated = $updater->update($report);

        $this->assertEquals($content, $updated);
    }
}