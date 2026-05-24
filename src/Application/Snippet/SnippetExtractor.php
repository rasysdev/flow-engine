<?php

declare(strict_types=1);

namespace FlowEngine\Application\Snippet;

/**
 * Extracts code snippets on-demand from source files for AI/MCP responses.
 *
 * @api
 */
final class SnippetExtractor
{
    public const DEFAULT_MAX_LINES = 30;

    public static function extract(
        string $file,
        ?int $startLine,
        ?int $endLine,
        int $maxLines = self::DEFAULT_MAX_LINES
    ): ?string {
        if ($startLine === null || $startLine < 1 || $maxLines < 1) {
            return null;
        }

        if (!is_file($file) || !is_readable($file)) {
            return null;
        }

        $lines = @file($file, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return null;
        }

        $totalLines = count($lines);
        if ($startLine > $totalLines) {
            return null;
        }

        $startIdx = $startLine - 1;
        $maxAllowedEnd = $startIdx + $maxLines - 1;

        if ($endLine !== null && $endLine >= $startLine) {
            $endIdx = min($endLine - 1, $maxAllowedEnd, $totalLines - 1);
            $truncated = ($endLine - 1) > $endIdx;
        } else {
            $endIdx = min($maxAllowedEnd, $totalLines - 1);
            $truncated = false;
        }

        $slice = array_slice($lines, $startIdx, $endIdx - $startIdx + 1);
        $slice = self::dedent($slice);

        if ($truncated) {
            $slice[] = '// ... (truncated)';
        }

        return implode("\n", $slice);
    }

    /**
     * @param string[] $lines
     * @return string[]
     */
    private static function dedent(array $lines): array
    {
        $minIndent = PHP_INT_MAX;

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $stripped = ltrim($line);
            $indent = strlen($line) - strlen($stripped);
            if ($indent < $minIndent) {
                $minIndent = $indent;
            }
        }

        if ($minIndent === PHP_INT_MAX || $minIndent === 0) {
            return $lines;
        }

        return array_map(
            static fn(string $line): string => trim($line) === '' ? '' : substr($line, $minIndent),
            $lines
        );
    }
}
