<?php

declare(strict_types=1);

namespace FlowEngine\Tests\Unit\Application\Snippet;

use FlowEngine\Application\Snippet\SnippetExtractor;
use PHPUnit\Framework\TestCase;

final class SnippetExtractorTest extends TestCase
{
    private ?string $tmpFile = null;

    protected function tearDown(): void
    {
        if ($this->tmpFile !== null && is_file($this->tmpFile)) {
            unlink($this->tmpFile);
            $this->tmpFile = null;
        }
    }

    private function writeTmp(string $content): string
    {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'snip_test_');
        file_put_contents($this->tmpFile, $content);
        return $this->tmpFile;
    }

    public function test_returns_null_when_start_line_is_null(): void
    {
        $file = $this->writeTmp("line1\nline2\n");
        $result = SnippetExtractor::extract($file, null, null);
        self::assertNull($result);
    }

    public function test_returns_null_when_file_missing(): void
    {
        $result = SnippetExtractor::extract('/nonexistent/path/file.php', 1, null);
        self::assertNull($result);
    }

    public function test_extracts_range_between_start_and_end(): void
    {
        $file = $this->writeTmp("line1\nline2\nline3\nline4\n");
        $result = SnippetExtractor::extract($file, 2, 3);
        self::assertSame("line2\nline3", $result);
    }

    public function test_dedents_common_indentation(): void
    {
        $file = $this->writeTmp("    public function foo(): void\n    {\n        return;\n    }\n");
        $result = SnippetExtractor::extract($file, 1, 4);
        self::assertSame("public function foo(): void\n{\n    return;\n}", $result);
    }

    public function test_caps_at_max_lines(): void
    {
        $lines = implode("\n", range(1, 100)) . "\n";
        $file = $this->writeTmp($lines);
        $result = SnippetExtractor::extract($file, 1, null, 5);
        self::assertSame("1\n2\n3\n4\n5", $result);
    }

    public function test_marks_truncation_when_endline_exceeds_cap(): void
    {
        $lines = implode("\n", array_fill(0, 50, 'x')) . "\n";
        $file = $this->writeTmp($lines);
        $result = SnippetExtractor::extract($file, 1, 50, 3);
        self::assertStringContainsString('// ... (truncated)', $result);
    }

    public function test_no_endline_uses_default_cap(): void
    {
        $lines = implode("\n", range(1, 100)) . "\n";
        $file = $this->writeTmp($lines);
        $result = SnippetExtractor::extract($file, 1, null);
        $lineCount = substr_count((string) $result, "\n") + 1;
        self::assertLessThanOrEqual(SnippetExtractor::DEFAULT_MAX_LINES, $lineCount);
        self::assertStringNotContainsString('// ... (truncated)', (string) $result);
    }
}
