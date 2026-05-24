<?php

namespace FlowEngine\Application\AppMap;

/**
 * Parses OpenAPI 3.x / Swagger 2.x spec files (JSON or YAML) and extracts
 * the declared endpoints (method + path + optional summary).
 *
 * No external dependencies — JSON is parsed via json_decode; YAML is handled
 * by a minimal line-by-line state machine sufficient for the OpenAPI paths
 * structure. Full YAML compliance is not required or claimed.
 */
final class OpenApiContractParser
{
    private const HTTP_VERBS = ['get', 'post', 'put', 'patch', 'delete', 'head', 'options', 'trace'];

    /**
     * Parse an OpenAPI spec file and return all declared endpoints.
     *
     * Returns an empty array when the file is missing, unreadable, or contains
     * no recognisable path definitions.
     *
     * @return array<int, array{method: string, path: string, summary: string}>
     */
    public function parse(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [];
        }

        $content = file_get_contents($filePath);
        if ($content === false || trim($content) === '') {
            return [];
        }

        $firstChar = ltrim($content)[0] ?? '';
        if ($firstChar === '{' || $firstChar === '[') {
            return $this->parseJson($content);
        }

        return $this->parseYaml($content);
    }

    // -------------------------------------------------------------------------
    // JSON
    // -------------------------------------------------------------------------

    /**
     * @return array<int, array{method: string, path: string, summary: string}>
     */
    private function parseJson(string $content): array
    {
        $data = json_decode($content, true);
        if (!is_array($data) || !isset($data['paths']) || !is_array($data['paths'])) {
            return [];
        }

        return $this->extractPathsFromArray($data['paths']);
    }

    /**
     * @param array<string, mixed> $paths
     * @return array<int, array{method: string, path: string, summary: string}>
     */
    private function extractPathsFromArray(array $paths): array
    {
        $endpoints = [];

        foreach ($paths as $path => $pathItem) {
            if (!is_array($pathItem)) {
                continue;
            }

            foreach ($pathItem as $verb => $operation) {
                $verb = strtolower((string) $verb);
                if (!in_array($verb, self::HTTP_VERBS, true)) {
                    continue; // skip 'parameters', 'summary', 'description', etc.
                }
                if (!is_array($operation)) {
                    continue;
                }

                $summary = '';
                foreach (['summary', 'description', 'operationId'] as $field) {
                    if (isset($operation[$field]) && is_string($operation[$field]) && $operation[$field] !== '') {
                        $summary = $operation[$field];
                        break;
                    }
                }

                $endpoints[] = [
                    'method'  => strtoupper($verb),
                    'path'    => (string) $path,
                    'summary' => $summary,
                ];
            }
        }

        return $endpoints;
    }

    // -------------------------------------------------------------------------
    // YAML (minimal state-machine — OpenAPI paths structure only)
    // -------------------------------------------------------------------------

    /**
     * Minimal line-by-line YAML parser scoped to the OpenAPI `paths` block.
     *
     * Handles standard 2-space and 4-space indented OpenAPI YAML. Does not
     * support anchors, aliases, multi-line scalars, or flow-style syntax.
     *
     * @return array<int, array{method: string, path: string, summary: string}>
     */
    private function parseYaml(string $content): array
    {
        $lines = explode("\n", str_replace("\r\n", "\n", $content));

        $endpoints     = [];
        $inPaths       = false;
        $pathsIndent        = -1;
        $currentPath        = null;
        $pathIndent         = -1;
        $currentMethod      = null;
        $methodIndent       = -1;
        $opBodyIndent       = -1; // indent of direct operation-property keys (one level below method)
        $currentSummary     = '';

        // Flush the current path+method pair, then reset method state.
        $flush = function () use (&$endpoints, &$currentPath, &$currentMethod, &$methodIndent, &$opBodyIndent, &$currentSummary): void {
            if ($currentPath !== null && $currentMethod !== null) {
                $endpoints[] = [
                    'method'  => strtoupper($currentMethod),
                    'path'    => $currentPath,
                    'summary' => $currentSummary,
                ];
            }
            $currentMethod  = null;
            $methodIndent   = -1;
            $opBodyIndent   = -1;
            $currentSummary = '';
        };

        foreach ($lines as $line) {
            $stripped = rtrim($line);
            $ltrimmed = ltrim($stripped);

            // Skip blank lines and comments.
            if ($ltrimmed === '' || str_starts_with($ltrimmed, '#')) {
                continue;
            }

            $indent = strlen($stripped) - strlen($ltrimmed);

            // ── root-level key ────────────────────────────────────────────
            if ($indent === 0) {
                if (preg_match('/^paths\s*:/', $ltrimmed)) {
                    $inPaths     = true;
                    $pathsIndent = 0;
                    $currentPath = null;
                    $currentMethod = null;
                } elseif ($inPaths) {
                    // Leaving the paths block (another root-level key).
                    $flush();
                    break;
                }
                continue;
            }

            if (!$inPaths) {
                continue;
            }

            // ── path key: indented line starting with '/' ─────────────────
            if (str_starts_with($ltrimmed, '/') && str_ends_with($ltrimmed, ':')) {
                $flush();
                $currentPath    = rtrim($ltrimmed, ':');
                $pathIndent     = $indent;
                $currentMethod  = null;
                $methodIndent   = -1;
                $currentSummary = '';
                continue;
            }

            if ($currentPath === null || $indent <= $pathIndent) {
                continue;
            }

            // ── HTTP verb key ─────────────────────────────────────────────
            $verbCandidate = strtolower(rtrim($ltrimmed, ':'));
            if (in_array($verbCandidate, self::HTTP_VERBS, true) && str_ends_with($ltrimmed, ':')) {
                $flush();
                $currentMethod  = $verbCandidate;
                $methodIndent   = $indent;
                $opBodyIndent   = -1;
                $currentSummary = '';
                continue;
            }

            // ── operation property (summary / description / operationId) ──
            // Only look at the direct children of the method key (one indent
            // level deeper). Deeper keys belong to nested objects like
            // `responses` and must be ignored.
            if ($currentMethod !== null && $indent > $methodIndent) {
                if ($opBodyIndent === -1) {
                    $opBodyIndent = $indent; // first key seen under this method
                }
                if ($indent === $opBodyIndent && $currentSummary === '') {
                    if (preg_match('/^(summary|description|operationId)\s*:\s*(.+)$/', $ltrimmed, $m)) {
                        $currentSummary = trim($m[2], '"\'');
                    }
                }
            }
        }

        $flush();

        return $endpoints;
    }
}
