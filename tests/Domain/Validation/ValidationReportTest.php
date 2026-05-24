<?php

namespace FlowEngine\Tests\Domain\Validation;

use FlowEngine\Domain\Validation\Issue;
use FlowEngine\Domain\Validation\ValidationReport;
use PHPUnit\Framework\TestCase;

/**
 * Testes para ValidationReport aggregate.
 */
final class ValidationReportTest extends TestCase
{
    public function testEmptyReportHasNoIssues(): void
    {
        $report = new ValidationReport(
            docsFile: 'test.md',
            issues: [],
            totalReferences: 10
        );

        $this->assertFalse($report->hasIssues());
        $this->assertEquals(0, $report->issueCount());
        $this->assertTrue($report->isValid());
    }

    public function testReportWithIssuesHasIssues(): void
    {
        $issues = [
            Issue::fileNotFound('test.php'),
            Issue::methodNotFound('user.php', 'test'),
        ];

        $report = new ValidationReport(
            docsFile: 'test.md',
            issues: $issues,
            totalReferences: 10
        );

        $this->assertTrue($report->hasIssues());
        $this->assertEquals(2, $report->issueCount());
    }

    public function testGetIssuesFiltersByType(): void
    {
        $issues = [
            Issue::fileNotFound('test1.php'),
            Issue::fileNotFound('test2.php'),
            Issue::methodNotFound('user.php', 'test'),
        ];

        $report = new ValidationReport(
            docsFile: 'test.md',
            issues: $issues,
            totalReferences: 10
        );

        $fileIssues = $report->getIssues('FILE_NOT_FOUND');
        $methodIssues = $report->getIssues('METHOD_NOT_FOUND');

        $this->assertCount(2, $fileIssues);
        $this->assertCount(1, $methodIssues);
    }

    public function testGetStatsReturnsCorrectStatistics(): void
    {
        $issues = [
            Issue::fileNotFound('test1.php'),
            Issue::fileNotFound('test2.php'),
            Issue::methodNotFound('user.php', 'test'),
        ];

        $report = new ValidationReport(
            docsFile: 'test.md',
            issues: $issues,
            totalReferences: 10
        );

        $stats = $report->getStats();

        $this->assertEquals(10, $stats['total']);
        $this->assertEquals(3, $stats['issues']);
        $this->assertEquals(70.0, $stats['successRate']); // 7 ok de 10
        $this->assertArrayHasKey('byType', $stats);
        $this->assertEquals(2, $stats['byType']['FILE_NOT_FOUND']);
        $this->assertEquals(1, $stats['byType']['METHOD_NOT_FOUND']);
    }

    public function testSuccessRateIs100PercentWhenNoIssues(): void
    {
        $report = new ValidationReport(
            docsFile: 'test.md',
            issues: [],
            totalReferences: 10
        );

        $stats = $report->getStats();

        $this->assertEquals(100.0, $stats['successRate']);
    }

    public function testSuccessRateIs100PercentWhenZeroReferences(): void
    {
        $report = new ValidationReport(
            docsFile: 'test.md',
            issues: [],
            totalReferences: 0
        );

        $stats = $report->getStats();

        $this->assertEquals(100.0, $stats['successRate']);
    }

    public function testIsValidReturnsFalseForCriticalIssues(): void
    {
        $issues = [
            Issue::fileNotFound('test.php'),
        ];

        $report = new ValidationReport(
            docsFile: 'test.md',
            issues: $issues,
            totalReferences: 10
        );

        $this->assertFalse($report->isValid());
    }

    public function testIsValidReturnsTrueForNonCriticalIssues(): void
    {
        $issues = [
            Issue::alreadyDone('test.php', 'method', '@api'),
            Issue::conflict('test.php', 'method', '@api', '@internal'),
        ];

        $report = new ValidationReport(
            docsFile: 'test.md',
            issues: $issues,
            totalReferences: 10
        );

        $this->assertTrue($report->isValid());
    }

    public function testGetCriticalIssuesReturnsCriticalOnly(): void
    {
        $issues = [
            Issue::fileNotFound('test1.php'),
            Issue::methodNotFound('test2.php', 'test'),
            Issue::alreadyDone('test3.php', 'method', '@api'),
        ];

        $report = new ValidationReport(
            docsFile: 'test.md',
            issues: $issues,
            totalReferences: 10
        );

        $critical = $report->getCriticalIssues();

        $this->assertCount(2, $critical);
        $this->assertEquals('FILE_NOT_FOUND', $critical[0]->type);
        $this->assertEquals('METHOD_NOT_FOUND', $critical[1]->type);
    }

    public function testToArrayReturnsCompleteStructure(): void
    {
        $issues = [
            Issue::fileNotFound('test.php'),
        ];

        $report = new ValidationReport(
            docsFile: 'test.md',
            issues: $issues,
            totalReferences: 10
        );

        $array = $report->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('docsFile', $array);
        $this->assertArrayHasKey('totalReferences', $array);
        $this->assertArrayHasKey('issueCount', $array);
        $this->assertArrayHasKey('issues', $array);
        $this->assertArrayHasKey('stats', $array);
        $this->assertEquals('test.md', $array['docsFile']);
        $this->assertEquals(10, $array['totalReferences']);
        $this->assertEquals(1, $array['issueCount']);
    }
}