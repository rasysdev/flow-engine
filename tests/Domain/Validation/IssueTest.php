<?php

namespace FlowEngine\Tests\Domain\Validation;

use FlowEngine\Domain\Validation\Issue;
use PHPUnit\Framework\TestCase;

/**
 * Testes para Issue value object.
 */
final class IssueTest extends TestCase
{
    public function testFileNotFoundCreatesCorrectIssue(): void
    {
        $issue = Issue::fileNotFound('src/Missing/File.php');

        $this->assertEquals('FILE_NOT_FOUND', $issue->type);
        $this->assertEquals('src/Missing/File.php', $issue->file);
        $this->assertNull($issue->method);
        $this->assertNull($issue->expected);
        $this->assertNull($issue->current);
        $this->assertStringContainsString('File not found', $issue->message);
    }

    public function testMethodNotFoundCreatesCorrectIssue(): void
    {
        $issue = Issue::methodNotFound('src/User.php', 'missingMethod');

        $this->assertEquals('METHOD_NOT_FOUND', $issue->type);
        $this->assertEquals('src/User.php', $issue->file);
        $this->assertEquals('missingMethod', $issue->method);
        $this->assertNull($issue->expected);
        $this->assertNull($issue->current);
        $this->assertStringContainsString('missingMethod', $issue->message);
        $this->assertStringContainsString('not found', $issue->message);
    }

    public function testAlreadyDoneCreatesCorrectIssue(): void
    {
        $issue = Issue::alreadyDone('src/User.php', 'create', '@api');

        $this->assertEquals('ALREADY_DONE', $issue->type);
        $this->assertEquals('src/User.php', $issue->file);
        $this->assertEquals('create', $issue->method);
        $this->assertEquals('@api', $issue->expected);
        $this->assertEquals('@api', $issue->current);
        $this->assertStringContainsString('already has', $issue->message);
    }

    public function testConflictCreatesCorrectIssue(): void
    {
        $issue = Issue::conflict('src/User.php', 'create', '@api', '@internal');

        $this->assertEquals('CONFLICT', $issue->type);
        $this->assertEquals('src/User.php', $issue->file);
        $this->assertEquals('create', $issue->method);
        $this->assertEquals('@api', $issue->expected);
        $this->assertEquals('@internal', $issue->current);
        $this->assertStringContainsString('expected', $issue->message);
    }

    public function testIsTypeReturnsTrueForMatchingType(): void
    {
        $issue = Issue::fileNotFound('test.php');

        $this->assertTrue($issue->isType('FILE_NOT_FOUND'));
        $this->assertFalse($issue->isType('METHOD_NOT_FOUND'));
    }

    public function testToArrayReturnsCorrectStructure(): void
    {
        $issue = Issue::methodNotFound('src/User.php', 'test');
        $array = $issue->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('type', $array);
        $this->assertArrayHasKey('file', $array);
        $this->assertArrayHasKey('method', $array);
        $this->assertArrayHasKey('message', $array);
        $this->assertEquals('METHOD_NOT_FOUND', $array['type']);
        $this->assertEquals('src/User.php', $array['file']);
        $this->assertEquals('test', $array['method']);
    }
}