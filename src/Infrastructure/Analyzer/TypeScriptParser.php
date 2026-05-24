<?php

namespace FlowEngine\Infrastructure\Analyzer;

use FlowEngine\Application\DTO\SymbolDTO;
use FlowEngine\Domain\Flow\Edge;
use FlowEngine\Domain\Flow\Node;
use FlowEngine\Domain\Flow\NodeFactory;

/**
 * TypeScriptParser (prototype)
 *
 * Minimal, best-effort parser for TypeScript/JavaScript files:
 * - Collects classes, methods, and exported functions
 * - Detects NestJS-style decorators (@Controller, @Get, @Post, etc.)
 * - Detects fetch/axios HTTP calls as cross-language edges
 *
 * Language tag: 'typescript' for .ts/.tsx, 'javascript' for .js/.jsx
 */
final class TypeScriptParser implements FileParser
{
    public function __construct(
        private readonly NodeFactory $nodeFactory,
        private readonly string $projectRoot,
        private readonly string $language = 'typescript'
    ) {
    }

    /**
     * @return array{nodes: Node[], edges: Edge[]}
     */
    public function parse(string $file): array
    {
        $content = @file_get_contents($file);
        if ($content === false) {
            return ['nodes' => [], 'edges' => [], 'symbols' => []];
        }

        $lines = preg_split("/\r\n|\n|\r/", $content);
        if (!is_array($lines)) {
            return ['nodes' => [], 'edges' => [], 'symbols' => []];
        }

        $module = $this->moduleNameFromPath($file);

        $nodes = [];
        $edges = [];

        /** @var array<string, string> functionName => nodeId */
        $topLevel = [];

        $currentClass     = null;
        $classDepth       = null;
        $depth            = 0;

        /** @var array<array{type: string, value: string}> */
        $pendingDecorators = [];

        /** @var array<array{type: string, value: string}> */
        $classDecorators = [];

        /** @var array<int, array{startDepth: int, endLine: int|null}> Indexed by position in $nodes */
        $nodeTracking = [];

        // Pre-pass: collect local import statements for edge emission after node collection.
        $localImports = $this->parseImports($lines, $file);

        // Pre-pass: collect all symbols (imports including npm, exports, top-level identifiers).
        $symbols = $this->collectSymbols($lines, $file);

        // Pass 1: collect nodes.
        foreach ($lines as $idx => $line) {
            $lineNo = $idx + 1;
            $trim   = trim($line);

            // Track brace depth (capture pre-update value for endLine tracking)
            $depthBefore = $depth;
            $depth += substr_count($line, '{') - substr_count($line, '}');

            // Exit class scope when depth falls back to class entry depth
            if ($currentClass !== null && $classDepth !== null && $depth <= $classDepth) {
                $currentClass  = null;
                $classDepth    = null;
                $classDecorators = [];
            }

            // Decorator: @Controller('/path'), @Get('/sub'), @Injectable(), etc.
            if (preg_match('/^\s*@([A-Za-z]+)\s*\(?\s*[\'"]?([^\'")\s]*)[\'"]?/', $line, $dm)) {
                $pendingDecorators[] = ['type' => $dm[1], 'value' => $dm[2]];
                continue;
            }

            // Class declaration
            if (preg_match('/^(?:export\s+)?(?:abstract\s+)?class\s+([A-Za-z_$][\w$]*)/', $trim, $m)) {
                $currentClass     = $m[1];
                $classDepth       = $depth - 1; // depth was already incremented for the opening {
                $classDecorators  = $pendingDecorators;
                $pendingDecorators = [];
                continue;
            }

            // Export default function: export default function foo() or anonymous
            if (preg_match('/^export\s+default\s+(?:async\s+)?function\s+([A-Za-z_$][\w$]*)\s*[(<]/', $trim, $m)) {
                $fn       = $m[1];
                $metadata = $this->withInferredFunctionEntrypointMetadata(
                    $this->buildFunctionMetadata($pendingDecorators, []),
                    $file,
                    $module,
                    $fn
                );
                $node     = $this->nodeFactory->create($module, $fn, $file, $lineNo, $this->language, $metadata ?: null);
                $nodes[]  = $node;
                $nodeTracking[count($nodes) - 1] = $this->initialTracking($trim, $depthBefore, $lineNo);
                $topLevel[$fn] = $node->id();
                $pendingDecorators = [];
                continue;
            }

            // Export function: export function foo() or export async function foo()
            if (preg_match('/^export\s+(?:async\s+)?function\s+([A-Za-z_$][\w$]*)\s*\(/', $trim, $m)) {
                $fn       = $m[1];
                $metadata = $this->withInferredFunctionEntrypointMetadata(
                    $this->buildFunctionMetadata($pendingDecorators, []),
                    $file,
                    $module,
                    $fn
                );
                $node     = $this->nodeFactory->create($module, $fn, $file, $lineNo, $this->language, $metadata ?: null);
                $nodes[]  = $node;
                $nodeTracking[count($nodes) - 1] = $this->initialTracking($trim, $depthBefore, $lineNo);
                $topLevel[$fn] = $node->id();
                $pendingDecorators = [];
                continue;
            }

            // Export arrow const: export const foo = (async)? (
            if (preg_match('/^export\s+const\s+([A-Za-z_$][\w$]*)\s*=\s*(?:async\s*)?\(/', $trim, $m)) {
                $fn       = $m[1];
                $metadata = $this->withInferredFunctionEntrypointMetadata(
                    $this->buildFunctionMetadata($pendingDecorators, []),
                    $file,
                    $module,
                    $fn
                );
                $node     = $this->nodeFactory->create($module, $fn, $file, $lineNo, $this->language, $metadata ?: null);
                $nodes[]  = $node;
                $nodeTracking[count($nodes) - 1] = $this->initialTracking($trim, $depthBefore, $lineNo);
                $topLevel[$fn] = $node->id();
                $pendingDecorators = [];
                continue;
            }

            // Top-level non-exported function. Many TS/JS CLIs and servers expose main/start/bootstrap
            // functions without exporting them.
            if ($depthBefore === 0 && preg_match('/^(?:async\s+)?function\s+([A-Za-z_$][\w$]*)\s*\(/', $trim, $m)) {
                $fn = $m[1];
                $metadata = $this->withInferredFunctionEntrypointMetadata([], $file, $module, $fn);
                $node = $this->nodeFactory->create($module, $fn, $file, $lineNo, $this->language, $metadata ?: null);
                $nodes[] = $node;
                $nodeTracking[count($nodes) - 1] = $this->initialTracking($trim, $depthBefore, $lineNo);
                $topLevel[$fn] = $node->id();
                $pendingDecorators = [];
                continue;
            }

            // Top-level non-exported arrow const.
            if ($depthBefore === 0 && preg_match('/^const\s+([A-Za-z_$][\w$]*)\s*=\s*(?:async\s*)?\(/', $trim, $m)) {
                $fn = $m[1];
                if ($this->looksLikeArrowProperty($trim)) {
                    $metadata = $this->withInferredFunctionEntrypointMetadata([], $file, $module, $fn);
                    $node = $this->nodeFactory->create($module, $fn, $file, $lineNo, $this->language, $metadata ?: null);
                    $nodes[] = $node;
                    $nodeTracking[count($nodes) - 1] = $this->initialTracking($trim, $depthBefore, $lineNo);
                    $topLevel[$fn] = $node->id();
                    $pendingDecorators = [];
                    continue;
                }
            }

            // Class method or arrow property (must be inside a class)
            if ($currentClass !== null && $depth > $classDepth) {
                $className = $module . '.' . $currentClass;

                // Class method: [modifiers] methodName( or [modifiers] methodName<
                if (preg_match(
                    '/^\s+(?:async\s+)?(?:(?:public|private|protected|readonly|static|abstract|override)\s+)*([A-Za-z_$][\w$]*)\s*[(<]/',
                    $line,
                    $m
                )) {
                    $name = $m[1];
                    // Skip keywords that are not method names
                    if (!in_array($name, ['if', 'for', 'while', 'switch', 'return', 'const', 'let', 'var', 'new', 'throw', 'catch', 'try'], true)
                        && $this->looksLikeMethodDeclaration($trim)
                    ) {
                        $metadata = $this->buildMethodMetadata($pendingDecorators, $classDecorators);
                        $node     = $this->nodeFactory->create($className, $name, $file, $lineNo, $this->language, $metadata ?: null);
                        $nodes[]  = $node;
                        $nodeTracking[count($nodes) - 1] = $this->initialTracking($trim, $depthBefore, $lineNo);
                        $pendingDecorators = [];
                        continue;
                    }
                }

                // Arrow property: [readonly] name = (async)? (
                if (preg_match('/^\s+(?:readonly\s+)?([A-Za-z_$][\w$]*)\s*=\s*(?:async\s*)?\(/', $line, $m)) {
                    $name = $m[1];
                    if (!in_array($name, ['if', 'for', 'while', 'switch', 'return', 'const', 'let', 'var'], true)
                        && $this->looksLikeArrowProperty($trim)
                    ) {
                        $metadata = $this->buildMethodMetadata($pendingDecorators, $classDecorators);
                        $node     = $this->nodeFactory->create($className, $name, $file, $lineNo, $this->language, $metadata ?: null);
                        $nodes[]  = $node;
                        $nodeTracking[count($nodes) - 1] = $this->initialTracking($trim, $depthBefore, $lineNo);
                        $pendingDecorators = [];
                        continue;
                    }
                }
            }

            // Reset pending decorators if line is not a decorator and not a blank/comment
            if ($pendingDecorators !== [] && $trim !== '' && !str_starts_with($trim, '//') && !str_starts_with($trim, '*')) {
                $pendingDecorators = [];
            }

            // Close any tracked nodes whose body returned to its startDepth on this line.
            // Two-state tracking: bodyOpened=false means we're still in a multi-line signature
            // and must wait for `{` before considering depth returns as a real close.
            foreach ($nodeTracking as $nodeIdx => $tracking) {
                if ($tracking['endLine'] !== null) {
                    continue;
                }
                if (!$tracking['bodyOpened']) {
                    if ($depth > $tracking['startDepth']) {
                        $nodeTracking[$nodeIdx]['bodyOpened'] = true;
                    }
                    continue;
                }
                if ($depth <= $tracking['startDepth']) {
                    $nodeTracking[$nodeIdx]['endLine'] = $lineNo;
                }
            }
        }

        // Reconstitute nodes with endLine in metadata for those that closed during Pass 1.
        foreach ($nodeTracking as $idx => $tracking) {
            if ($tracking['endLine'] === null) {
                continue;
            }
            $old = $nodes[$idx];
            $newMetadata = ($old->metadata() ?? []) + ['endLine' => $tracking['endLine']];
            $nodes[$idx] = $this->nodeFactory->create(
                $old->class(),
                $old->method(),
                $old->file(),
                $old->line(),
                $old->language(),
                $newMetadata
            );
        }

        // Pass 2: collect edges.
        $currentNodeId = null;
        $currentClass  = null;
        $classDepth    = null;
        $depth         = 0;

        foreach ($lines as $idx => $line) {
            $trim = trim($line);

            $depth += substr_count($line, '{') - substr_count($line, '}');

            if ($currentClass !== null && $classDepth !== null && $depth <= $classDepth) {
                $currentClass = null;
                $classDepth   = null;
            }

            // Class declaration
            if (preg_match('/^(?:export\s+)?(?:abstract\s+)?class\s+([A-Za-z_$][\w$]*)/', $trim, $m)) {
                $currentClass = $m[1];
                $classDepth   = $depth - 1;
                $currentNodeId = null;
                continue;
            }

            // Export default function
            if (preg_match('/^export\s+default\s+(?:async\s+)?function\s+([A-Za-z_$][\w$]*)\s*[(<]/', $trim, $m)) {
                $currentNodeId = $topLevel[$m[1]] ?? null;
                continue;
            }

            // Export function
            if (preg_match('/^export\s+(?:async\s+)?function\s+([A-Za-z_$][\w$]*)\s*\(/', $trim, $m)) {
                $currentNodeId = $topLevel[$m[1]] ?? null;
                continue;
            }

            // Export arrow const
            if (preg_match('/^export\s+const\s+([A-Za-z_$][\w$]*)\s*=\s*(?:async\s*)?\(/', $trim, $m)) {
                $currentNodeId = $topLevel[$m[1]] ?? null;
                continue;
            }

            // Top-level non-exported function
            if (preg_match('/^(?:async\s+)?function\s+([A-Za-z_$][\w$]*)\s*\(/', $trim, $m)) {
                if (isset($topLevel[$m[1]])) {
                    $currentNodeId = $topLevel[$m[1]];
                    continue;
                }
            }

            // Top-level non-exported arrow const
            if (preg_match('/^const\s+([A-Za-z_$][\w$]*)\s*=\s*(?:async\s*)?\(/', $trim, $m)) {
                if (isset($topLevel[$m[1]])) {
                    $currentNodeId = $topLevel[$m[1]];
                    continue;
                }
            }

            // Class method start
            if ($currentClass !== null && $depth > $classDepth) {
                if (preg_match(
                    '/^\s+(?:async\s+)?(?:(?:public|private|protected|readonly|static|abstract|override)\s+)*([A-Za-z_$][\w$]*)\s*[(<]/',
                    $line,
                    $m
                )) {
                    $name = $m[1];
                    if (!in_array($name, ['if', 'for', 'while', 'switch', 'return', 'const', 'let', 'var', 'new', 'throw', 'catch', 'try'], true)) {
                        $className     = $module . '.' . $currentClass;
                        $tmp           = $this->nodeFactory->create($className, $name, $file, ($idx + 1), $this->language);
                        $currentNodeId = $tmp->id();
                        continue;
                    }
                }
            }

            if ($currentNodeId === null) {
                continue;
            }

            // fetch('/url') → http:GET:{url}
            if (preg_match('/\bfetch\s*\(\s*[\'"]([^\'"]+)[\'"]/', $line, $m)) {
                $url = $m[1];
                $edges[] = new Edge($currentNodeId, 'http:GET:' . $url, 'fetch', 'http_call');
            }

            // axios.get/post/put/delete/patch('/url')
            if (preg_match('/\baxios\.(get|post|put|delete|patch)\s*\(\s*[\'"]([^\'"]+)[\'"]/', $line, $m)) {
                $method  = strtoupper($m[1]);
                $url     = $m[2];
                $edges[] = new Edge($currentNodeId, 'http:' . $method . ':' . $url, 'axios.' . $m[1], 'http_call');
            }

            // Intra-file call to known top-level functions
            if (preg_match_all('/\b([A-Za-z_$][\w$]*)\s*\(/', $trim, $allM)) {
                foreach ($allM[1] as $called) {
                    if (isset($topLevel[$called]) && $topLevel[$called] !== $currentNodeId) {
                        $edges[] = new Edge($currentNodeId, $topLevel[$called], $called, 'ts_call');
                    }
                }
            }
        }

        // Post-pass: emit virtual import_call edges from the first node in this file.
        // CrossLanguageEdgeDetector resolves ts_import:{module}::{symbol} targets to real node IDs.
        if ($localImports !== [] && $nodes !== []) {
            $fromNodeId = $nodes[0]->id();
            foreach ($localImports as $import) {
                foreach ($import['symbols'] as $symbol) {
                    $edges[] = new Edge(
                        $fromNodeId,
                        'ts_import:' . $import['resolvedModule'] . '::' . $symbol,
                        $symbol,
                        'import_call'
                    );
                }
            }
        }

        return ['nodes' => $nodes, 'edges' => $edges, 'symbols' => $symbols];
    }

    /**
     * Pre-pass: collect local import statements from the file.
     * Skips type-only imports, side-effect imports, and npm package specifiers.
     *
     * @param string[] $lines
     * @return array<int, array{symbols: string[], resolvedModule: string}>
     */
    private function parseImports(array $lines, string $file): array
    {
        $imports  = [];
        $buffer   = '';
        $buffering = false;

        foreach ($lines as $line) {
            $trim = trim($line);

            // Assemble multi-line import: import {\n  X,\n  Y,\n} from '...'
            if ($buffering) {
                $buffer .= ' ' . $trim;
                if (str_contains($trim, '}')) {
                    $buffering = false;
                    $this->processImportLine($buffer, $file, $imports);
                    $buffer = '';
                }
                continue;
            }

            if (!str_starts_with($trim, 'import ')) {
                continue;
            }

            // Skip type-only imports: import type { ... }
            if (preg_match('/^import\s+type\b/', $trim)) {
                continue;
            }

            if (str_contains($trim, 'from')) {
                $this->processImportLine($trim, $file, $imports);
            } elseif (str_contains($trim, '{') && !str_contains($trim, '}')) {
                // Multi-line named import starts here
                $buffering = true;
                $buffer    = $trim;
            }
        }

        return $imports;
    }

    /**
     * Parse one (possibly assembled multi-line) import statement and append to $imports.
     *
     * @param array<int, array{symbols: string[], resolvedModule: string}> $imports
     */
    private function processImportLine(string $line, string $file, array &$imports): void
    {
        if (!preg_match('/from\s+[\'"]([^\'"]+)[\'"]/', $line, $fromMatch)) {
            return;
        }

        $specifier = $fromMatch[1];

        // Skip npm packages (no relative prefix and not the @/ project alias)
        if (!str_starts_with($specifier, '.') && !str_starts_with($specifier, '@/')) {
            return;
        }

        // Skip non-code asset imports
        if (preg_match('/\.(css|scss|sass|less|json|svg|png|jpg|gif|woff|ttf|eot)$/', $specifier)) {
            return;
        }

        $resolvedModule = $this->resolveModulePath($file, $specifier);
        if ($resolvedModule === null) {
            return;
        }

        $symbols = [];

        // Named import: import { X, Y as Z } from '...'
        if (preg_match('/import\s+\{([^}]+)\}/', $line, $namedMatch)) {
            foreach (preg_split('/\s*,\s*/', trim($namedMatch[1])) as $sym) {
                $sym = trim($sym);
                // Skip inline type-only members: { type Foo, Bar }
                if (str_starts_with($sym, 'type ')) {
                    continue;
                }
                // Strip alias "X as localName" → keep the exported name X
                $sym = trim((string) preg_replace('/\s+as\s+\S+.*/', '', $sym));
                if ($sym !== '') {
                    $symbols[] = $sym;
                }
            }
        }

        // Default import: import X from '...' (no braces, no *)
        if ($symbols === [] && preg_match('/^import\s+([A-Za-z_$][\w$]*)\s+from/', $line, $defMatch)) {
            $symbols[] = $defMatch[1];
        }

        // Namespace import: import * as X from '...'
        if ($symbols === [] && preg_match('/import\s+\*\s+as\s+([A-Za-z_$][\w$]*)/', $line, $nsMatch)) {
            // Wildcard: resolver will pick the first exported symbol from the target module
            $symbols[] = '__namespace';
        }

        if ($symbols === []) {
            return;
        }

        $imports[] = ['symbols' => $symbols, 'resolvedModule' => $resolvedModule];
    }

    /**
     * Collect all top-level symbols from a TS/JS file:
     * - All import statements (including npm packages) → kind=import, sourceModule=specifier
     * - export function / export default function → kind=export_function
     * - export const (arrow fn or plain value) → kind=export_const
     * - top-level function (no export) → kind=function
     * - top-level const (no export, no arrow fn) → kind=const
     *
     * @param string[] $lines
     * @return SymbolDTO[]
     */
    private function collectSymbols(array $lines, string $file): array
    {
        $symbols    = [];
        $depth      = 0;
        $buffer     = '';
        $buffering  = false;
        $bufferLine = 0;

        foreach ($lines as $idx => $line) {
            $lineNo      = $idx + 1;
            $trim        = trim($line);
            $depthBefore = $depth;

            $depth += substr_count($line, '{') - substr_count($line, '}');

            // Assemble multi-line import for symbol collection
            if ($buffering) {
                $buffer .= ' ' . $trim;
                if (str_contains($trim, '}')) {
                    $buffering = false;
                    $this->processImportLineForSymbols($buffer, $bufferLine, $file, $symbols);
                    $buffer    = '';
                    $bufferLine = 0;
                }
                continue;
            }

            // Only collect top-level declarations (depth was 0 at start of this line)
            if ($depthBefore !== 0) {
                continue;
            }

            // Import statement (includes npm packages — unlike parseImports which filters them)
            if (str_starts_with($trim, 'import ')) {
                // Skip type-only imports
                if (preg_match('/^import\s+type\b/', $trim)) {
                    continue;
                }
                if (str_contains($trim, 'from')) {
                    $this->processImportLineForSymbols($trim, $lineNo, $file, $symbols);
                } elseif (str_contains($trim, '{') && !str_contains($trim, '}')) {
                    $buffering  = true;
                    $buffer     = $trim;
                    $bufferLine = $lineNo;
                }
                continue;
            }

            // export default function foo()
            if (preg_match('/^export\s+default\s+(?:async\s+)?function\s+([A-Za-z_$][\w$]*)\s*[(<]/', $trim, $m)) {
                $symbols[] = SymbolDTO::make($m[1], 'export_function', $file, $lineNo);
                continue;
            }

            // export function foo()
            if (preg_match('/^export\s+(?:async\s+)?function\s+([A-Za-z_$][\w$]*)\s*\(/', $trim, $m)) {
                $symbols[] = SymbolDTO::make($m[1], 'export_function', $file, $lineNo);
                continue;
            }

            // export const foo = (...) => or export const foo = value
            if (preg_match('/^export\s+const\s+([A-Za-z_$][\w$]*)/', $trim, $m)) {
                $symbols[] = SymbolDTO::make($m[1], 'export_const', $file, $lineNo);
                continue;
            }

            // Top-level non-exported function
            if (preg_match('/^(?:async\s+)?function\s+([A-Za-z_$][\w$]*)\s*\(/', $trim, $m)) {
                $symbols[] = SymbolDTO::make($m[1], 'function', $file, $lineNo);
                continue;
            }

            // Top-level non-exported const (skip arrow functions — they'd be caught above as export_const)
            if (preg_match('/^const\s+([A-Za-z_$][\w$]*)\s*=/', $trim, $m)) {
                // Only include plain consts, not arrow functions (to avoid duplicating flow nodes)
                if (!preg_match('/=\s*(?:async\s*)?\(/', $trim) && !preg_match('/=>\s*/', $trim)) {
                    $symbols[] = SymbolDTO::make($m[1], 'const', $file, $lineNo);
                }
            }
        }

        return $symbols;
    }

    /**
     * Parse one import line and append SymbolDTOs to $symbols.
     * Unlike processImportLine(), this includes npm package specifiers.
     *
     * @param SymbolDTO[] $symbols
     */
    private function processImportLineForSymbols(string $line, int $lineNo, string $file, array &$symbols): void
    {
        if (!preg_match('/from\s+[\'"]([^\'"]+)[\'"]/', $line, $fromMatch)) {
            return;
        }

        $specifier = $fromMatch[1];

        // Skip non-code asset imports
        if (preg_match('/\.(css|scss|sass|less|json|svg|png|jpg|gif|woff|ttf|eot)$/', $specifier)) {
            return;
        }

        $names = [];

        // Named imports: import { X, Y as Z } from '...' (also matches mixed: import Foo, { X } from '...')
        if (preg_match('/import\s+(?:[A-Za-z_$][\w$]*\s*,\s*)?\{([^}]+)\}/', $line, $namedMatch)) {
            foreach (preg_split('/\s*,\s*/', trim($namedMatch[1])) as $sym) {
                $sym = trim($sym);
                if (str_starts_with($sym, 'type ')) {
                    continue;
                }
                // Keep local alias after "as": import { Foo as Bar } → use "Bar" (local name)
                if (preg_match('/^(\S+)\s+as\s+(\S+)$/', $sym, $aliasM)) {
                    $sym = $aliasM[2];
                }
                if ($sym !== '') {
                    $names[] = $sym;
                }
            }
        }

        // Default import: import X from '...' or mixed import X, { ... } from '...'
        if (preg_match('/^import\s+([A-Za-z_$][\w$]*)\s*(?:,\s*\{|from)/', $line, $defMatch)) {
            $names[] = $defMatch[1];
        }

        // Namespace import: import * as X from '...'
        if ($names === [] && preg_match('/import\s+\*\s+as\s+([A-Za-z_$][\w$]*)/', $line, $nsMatch)) {
            $names[] = $nsMatch[1];
        }

        foreach ($names as $name) {
            $symbols[] = SymbolDTO::make($name, 'import', $file, $lineNo, $specifier);
        }
    }

    /**
     * Resolve an import specifier to a dot-notation module path relative to the project root.
     * Returns null for unresolvable paths (e.g. when the resolved path falls outside the project root).
     */
    private function resolveModulePath(string $currentFile, string $specifier): ?string
    {
        $root = str_replace(['\\', '/'], '/', rtrim($this->projectRoot, '/\\'));
        $dir  = str_replace(['\\', '/'], '/', dirname($currentFile));

        if (str_starts_with($specifier, '@/')) {
            // @/ alias: Next.js / Vite convention maps @/ to {root}/src/
            $rest     = substr($specifier, 2);
            $resolved = $root . '/src/' . $rest;
        } else {
            $resolved = $this->normalizePath($dir . '/' . ltrim($specifier, '/'));
        }

        // Strip TS/JS extension if present
        $resolved = (string) preg_replace('/\.(tsx?|jsx?)$/', '', $resolved);

        $rootSlash = $root . '/';
        if (str_starts_with($resolved, $rootSlash)) {
            $relative = substr($resolved, strlen($rootSlash));
        } else {
            return null;
        }

        $relative = trim($relative, '/');
        if ($relative === '') {
            return null;
        }

        return str_replace('/', '.', $relative);
    }

    /**
     * Resolve . and .. segments in a forward-slash path.
     */
    private function normalizePath(string $path): string
    {
        $leading = str_starts_with($path, '/') ? '/' : '';
        $parts   = explode('/', $path);
        $result  = [];

        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($result);
            } else {
                $result[] = $part;
            }
        }

        return $leading . implode('/', $result);
    }

    /**
     * Build metadata for a class method by merging class-level and method-level decorators.
     *
     * @param array<array{type: string, value: string}> $methodDecorators
     * @param array<array{type: string, value: string}> $classDecorators
     * @return array<string, mixed>
     */
    private function buildMethodMetadata(array $methodDecorators, array $classDecorators): array
    {
        $meta = [];

        // Class-level controller path
        $classPath = '';
        foreach ($classDecorators as $dec) {
            if ($dec['type'] === 'Controller') {
                $classPath = $dec['value'];
                $meta['framework'] = 'nestjs';
            }
            if (in_array($dec['type'], ['Injectable', 'Component', 'Pipe'], true)) {
                $meta['di'] = true;
            }
        }

        // Method-level HTTP decorators
        $httpMethods = ['Get', 'Post', 'Put', 'Delete', 'Patch'];
        foreach ($methodDecorators as $dec) {
            if (in_array($dec['type'], $httpMethods, true)) {
                $meta['http_method'] = strtoupper($dec['type']);
                $meta['http_path']   = $classPath . $dec['value'];
                $meta['entrypoint_type'] = 'http';
            }
            if (in_array($dec['type'], ['Injectable', 'Component', 'Pipe'], true)) {
                $meta['di'] = true;
            }
        }

        return $meta;
    }

    /**
     * Build metadata for standalone/exported functions.
     *
     * @param array<array{type: string, value: string}> $decorators
     * @param array<array{type: string, value: string}> $classDecorators
     * @return array<string, mixed>
     */
    private function buildFunctionMetadata(array $decorators, array $classDecorators): array
    {
        $meta = [];

        foreach ($decorators as $dec) {
            if ($dec['type'] === 'Controller') {
                $meta['http_path'] = $dec['value'];
                $meta['framework'] = 'nestjs';
            }
            $httpMethods = ['Get', 'Post', 'Put', 'Delete', 'Patch'];
            if (in_array($dec['type'], $httpMethods, true)) {
                $meta['http_method'] = strtoupper($dec['type']);
                $meta['http_path']   = $dec['value'];
                $meta['entrypoint_type'] = 'http';
            }
            if (in_array($dec['type'], ['Injectable', 'Component', 'Pipe'], true)) {
                $meta['di'] = true;
            }
        }

        return $meta;
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function withInferredFunctionEntrypointMetadata(array $metadata, string $file, string $module, string $functionName): array
    {
        if (isset($metadata['entrypoint_type'])) {
            return $metadata;
        }

        $lowerName = strtolower($functionName);
        $normalizedFile = str_replace('\\', '/', strtolower($file));
        $normalizedModule = strtolower($module);

        if (in_array($functionName, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'], true)) {
            $metadata['entrypoint_type'] = 'http';
            $metadata['http_method'] = $functionName;
            return $metadata;
        }

        if (in_array($lowerName, ['handler', 'loader', 'action', 'middleware'], true)
            && (str_contains($normalizedFile, '/api/') || str_contains($normalizedFile, '/routes/') || str_contains($normalizedModule, '.route'))
        ) {
            $metadata['entrypoint_type'] = 'http';
            return $metadata;
        }

        if (in_array($lowerName, ['main', 'start', 'bootstrap', 'serve', 'runserver'], true)) {
            $metadata['entrypoint_type'] = 'cli';
            return $metadata;
        }

        if (($this->language === 'typescript' || $this->language === 'javascript')
            && str_ends_with($normalizedFile, '.tsx')
            && preg_match('/^[A-Z]/', $functionName) === 1
        ) {
            $metadata['entrypoint_type'] = 'ui';
        }

        return $metadata;
    }

    private function moduleNameFromPath(string $file): string
    {
        // Normalize both paths to forward slashes before comparing so that
        // mixed separators (e.g. Windows sys_get_temp_dir() + '/' . $name)
        // do not cause str_starts_with to fail.
        $root           = str_replace(['\\', '/'], '/', rtrim($this->projectRoot, '/\\'));
        $normalizedFile = str_replace(['\\', '/'], '/', $file);

        $relative = $normalizedFile;
        if (str_starts_with($normalizedFile, $root . '/')) {
            $relative = substr($normalizedFile, strlen($root . '/'));
        }

        // Strip known TS/JS extensions
        $relative = preg_replace('/\.(tsx?|jsx?)$/', '', $relative) ?? $relative;

        $relative = trim($relative, '/');
        if ($relative === '') {
            return 'module';
        }

        return str_replace('/', '.', $relative);
    }

    /**
     * Compute the initial endLine tracking state for a node based on its declaration line.
     *
     * Uses the last non-whitespace char of the trimmed declaration line as a discriminator:
     *  - `{`    body opens this line, multi-line body expected
     *  - `}`    inline body closes this line
     *  - `;`    abstract/overload, expression-bodied arrow, or inline statement — no further body
     *  - `,`,`(` multi-line signature continues; body has not opened yet
     *
     * Avoids confusing TypeScript type literals (e.g. `arg: { id: string },`) with function bodies.
     *
     * @return array{startDepth: int, endLine: int|null, bodyOpened: bool}
     */
    private function initialTracking(string $trim, int $depthBefore, int $lineNo): array
    {
        // Expression-bodied arrow without `{` (relies on ASI for the trailing semicolon):
        // `const f = (x) => x + 1` — body is the rest of the same line.
        if (str_contains($trim, '=>') && !str_contains($trim, '{')) {
            return ['startDepth' => $depthBefore, 'endLine' => $lineNo, 'bodyOpened' => true];
        }

        $lastChar = $trim !== '' ? $trim[strlen($trim) - 1] : '';

        return match ($lastChar) {
            '{'      => ['startDepth' => $depthBefore, 'endLine' => null,    'bodyOpened' => true],
            '}', ';' => ['startDepth' => $depthBefore, 'endLine' => $lineNo, 'bodyOpened' => true],
            default  => ['startDepth' => $depthBefore, 'endLine' => null,    'bodyOpened' => false],
        };
    }

    /**
     * Distinguishes a method declaration from a function call.
     * Declarations end with `{` (body open), `}` (inline body like `foo() {}`),
     * `,`/`(` (multi-line signature), or `;` preceded by a non-`)` char
     * (abstract/overload with return type). Calls typically end with `);` or `),`.
     */
    private function looksLikeMethodDeclaration(string $trimmed): bool
    {
        if ($trimmed === '') {
            return false;
        }
        $last = $trimmed[strlen($trimmed) - 1];
        return match ($last) {
            '{', '}', ',', '(' => true,
            ';' => strlen($trimmed) >= 2 && $trimmed[strlen($trimmed) - 2] !== ')',
            default => false,
        };
    }

    /**
     * Distinguishes an arrow property declaration from an expression assignment.
     * Arrow properties contain `=>` on the same line (single-line form) or
     * end with `,`/`(` (multi-line signature). Regular assignments like
     * `foo = (x + y) * 2;` do not contain `=>`.
     */
    private function looksLikeArrowProperty(string $trimmed): bool
    {
        if ($trimmed === '') {
            return false;
        }
        if (str_contains($trimmed, '=>')) {
            return true;
        }
        $last = $trimmed[strlen($trimmed) - 1];
        return $last === ',' || $last === '(';
    }
}
