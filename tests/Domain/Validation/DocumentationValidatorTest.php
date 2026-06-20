<?php

namespace FlowEngine\Tests\Domain\Validation;

use FlowEngine\Domain\Validation\DocumentationValidator;
use FlowEngine\Domain\Contracts\Flow;
use FlowEngine\Domain\Flow\Node;
use PHPUnit\Framework\TestCase;

/**
 * Testes para DocumentationValidator service.
 */
final class DocumentationValidatorTest extends TestCase
{
    private string $tempFile;
    private string $tempProjectRoot;

    protected function setUp(): void
    {
        $this->tempFile = sys_get_temp_dir() . '/test_docs_' . uniqid() . '.md';
        $this->tempProjectRoot = sys_get_temp_dir() . '/test_project_' . uniqid();
        
        if (!is_dir($this->tempProjectRoot)) {
            mkdir($this->tempProjectRoot, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
        
        if (is_dir($this->tempProjectRoot)) {
            $this->removeDirectory($this->tempProjectRoot);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        
        rmdir($dir);
    }

    public function testValidateDetectsFileNotFound(): void
    {
        $content = <<<MD
        # Documentation
        
        src/Existing/File.php
        src/Missing/File.php
        MD;

        file_put_contents($this->tempFile, $content);

        // Criar apenas o arquivo existente
        mkdir($this->tempProjectRoot . '/src/Existing', 0777, true);
        touch($this->tempProjectRoot . '/src/Existing/File.php');

        $flow = $this->createFakeFlow([]);

        $validator = new DocumentationValidator(
            $flow,
            $this->tempFile,
            $this->tempProjectRoot
        );

        $report = $validator->validate();

        $this->assertTrue($report->hasIssues());
        $fileIssues = $report->getIssues('FILE_NOT_FOUND');
        $this->assertCount(1, $fileIssues);
        $this->assertEquals('src/Missing/File.php', $fileIssues[0]->file);
    }

    public function testValidateDetectsMethodNotFound(): void
    {
        $content = <<<MD
        # Documentation
        
        src/User.php
        - @api existingMethod()
        - @api missingMethod()
        MD;

        file_put_contents($this->tempFile, $content);

        // Criar arquivo
        mkdir($this->tempProjectRoot . '/src', 0777, true);
        touch($this->tempProjectRoot . '/src/User.php');

        // Criar Node de domínio com existingMethod
        $node = new Node(
            class: 'User',
            method: 'existingMethod',
            file: $this->tempProjectRoot . '/src/User.php',
            line: 10
        );

        $flow = $this->createFakeFlow([$node]);

        $validator = new DocumentationValidator(
            $flow,
            $this->tempFile,
            $this->tempProjectRoot
        );

        $report = $validator->validate();

        $methodIssues = $report->getIssues('METHOD_NOT_FOUND');
        $this->assertCount(1, $methodIssues);
        $this->assertEquals('missingMethod', $methodIssues[0]->method);
    }

    public function testValidateHandlesEmptyDocumentation(): void
    {
        file_put_contents($this->tempFile, "# Empty Documentation");

        $flow = $this->createFakeFlow([]);

        $validator = new DocumentationValidator(
            $flow,
            $this->tempFile,
            $this->tempProjectRoot
        );

        $report = $validator->validate();

        $this->assertFalse($report->hasIssues());
        $this->assertEquals(0, $report->totalReferences);
    }

    public function testValidateHandlesComplexDocumentation(): void
    {
        $content = <<<MD
        # Complex Documentation
        
        ## Section 1
        
        src/Domain/User.php
        - @api create()
        - @internal validate()
        
        ## Section 2
        
        src/Application/UserService.php
        - @api register()
        
        src/Missing.php
        - @api test()
        MD;

        file_put_contents($this->tempFile, $content);

        // Criar arquivos existentes
        mkdir($this->tempProjectRoot . '/src/Domain', 0777, true);
        mkdir($this->tempProjectRoot . '/src/Application', 0777, true);
        touch($this->tempProjectRoot . '/src/Domain/User.php');
        touch($this->tempProjectRoot . '/src/Application/UserService.php');

        $flow = $this->createFakeFlow([]);

        $validator = new DocumentationValidator(
            $flow,
            $this->tempFile,
            $this->tempProjectRoot
        );

        $report = $validator->validate();

        // Deve encontrar apenas src/Missing.php
        $this->assertTrue($report->hasIssues());
        $fileIssues = $report->getIssues('FILE_NOT_FOUND');
        $this->assertCount(1, $fileIssues);
        $this->assertEquals('src/Missing.php', $fileIssues[0]->file);
    }

    /**
     * Cria um Flow fake com os nodes fornecidos.
     *
     * @param Node[] $nodes
     */
    private function createFakeFlow(array $nodes): Flow
    {
        $flow = $this->createStub(Flow::class);
        $flow->method('nodes')->willReturn($nodes);
        return $flow;
    }
}
