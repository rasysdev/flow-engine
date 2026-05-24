<?php

namespace FlowEngine\Infrastructure\BugDetection;

use FlowEngine\Domain\Analysis\Bug;
use FlowEngine\Domain\Contracts\BugScannerPort;

/**
 * Scans Python files for common bug patterns using line-based heuristics.
 * No external AST dependency — pure regex/line analysis.
 *
 * Detected patterns:
 * - python_exception_swallowing : bare except or `except Exception[as e]:` whose body
 *                                 contains only pass, comments, or ellipsis
 * - python_mutable_default_arg  : def foo(bar=[]) or def foo(bar={})
 * - python_missing_return_type  : public function with no `->` annotation
 *                                 (excludes _private and __dunder__ names)
 * - python_unchecked_subprocess : os.system()/subprocess.run/call/Popen() used as
 *                                 a bare statement (result not captured)
 *
 * NodeID format: `module::functionName` where module = basename($file, '.py')
 */
final class PythonBugScanner implements BugScannerPort
{
    /** @param string[] $files Absolute Python file paths */
    public function __construct(private array $files) {}

    /**
     * @return array<int, array{nodeId:string, type:string, description:string, confidence:float, file:string, line:int|null}>
     */
    public function scan(): array
    {
        $findings = [];

        foreach ($this->files as $file) {
            $code = @file_get_contents($file);
            if ($code === false || trim($code) === '') {
                continue;
            }

            $lines  = explode("\n", $code);
            $module = basename($file, '.py');

            foreach ($this->scanLines($lines, $module, $file) as $finding) {
                $findings[] = $finding;
            }
        }

        return $findings;
    }

    // -------------------------------------------------------------------------
    // Core line scanner
    // -------------------------------------------------------------------------

    /**
     * @param  string[] $lines
     * @return array<int, array{nodeId:string, type:string, description:string, confidence:float, file:string, line:int|null}>
     */
    private function scanLines(array $lines, string $module, string $file): array
    {
        $findings = [];

        /**
         * Indent stack: list of ['name' => string, 'indent' => int]
         * Each entry is a `def` we have seen, in order.
         * When a new `def` appears at indent X, all stack entries with indent >= X are popped
         * (they ended before the new sibling/parent def).
         *
         * @var array<int, array{name:string, indent:int}> $indentStack
         */
        $indentStack     = [];
        $currentFunction = null;
        $total           = count($lines);

        for ($i = 0; $i < $total; $i++) {
            $raw     = $lines[$i];
            $trimmed = rtrim($raw);  // keep leading whitespace for indent

            // -----------------------------------------------------------------
            // `def` line — track function scope + check patterns on signature
            // -----------------------------------------------------------------
            if (preg_match('/^(\s*)def\s+(\w+)\s*\(/', $raw, $m)) {
                $defIndent = strlen($m[1]);
                $funcName  = $m[2];

                // Pop functions at the same or deeper indentation (siblings / nested-ended)
                while (!empty($indentStack) && end($indentStack)['indent'] >= $defIndent) {
                    array_pop($indentStack);
                }

                $indentStack[]   = ['name' => $funcName, 'indent' => $defIndent];
                $currentFunction = $funcName;

                // Mutable default arg
                if ($this->hasMutableDefaultArg($raw)) {
                    $findings[] = [
                        'nodeId'      => $this->nodeId($module, $funcName),
                        'type'        => Bug::TYPE_PYTHON_MUTABLE_DEFAULT,
                        'description' => "Function '{$funcName}' uses a mutable default argument ([] or {})",
                        'confidence'  => 0.95,
                        'file'        => $file,
                        'line'        => $i + 1,
                    ];
                }

                // Missing return type (public, non-dunder functions only)
                if ($this->isMissingReturnType($raw, $funcName)) {
                    $findings[] = [
                        'nodeId'      => $this->nodeId($module, $funcName),
                        'type'        => Bug::TYPE_PYTHON_MISSING_RETURN_TYPE,
                        'description' => "Function '{$funcName}' has no return type annotation (->)",
                        'confidence'  => 0.40,
                        'file'        => $file,
                        'line'        => $i + 1,
                    ];
                }

                continue;
            }

            // -----------------------------------------------------------------
            // Non-def line — resolve current function from indent stack
            // -----------------------------------------------------------------
            $lineIndent      = strlen($raw) - strlen(ltrim($raw));
            $currentFunction = null;

            foreach ($indentStack as $frame) {
                if ($lineIndent > $frame['indent']) {
                    $currentFunction = $frame['name'];
                }
            }

            $funcName = $currentFunction ?? 'module';

            // -----------------------------------------------------------------
            // Exception swallowing — `except` line
            // -----------------------------------------------------------------
            if (preg_match('/^\s*except\b/', $raw)) {
                if ($this->isExceptionSwallowing($lines, $i)) {
                    $findings[] = [
                        'nodeId'      => $this->nodeId($module, $funcName),
                        'type'        => Bug::TYPE_PYTHON_EXCEPTION_SWALLOWING,
                        'description' => 'Caught exception is silently swallowed (empty except or only pass)',
                        'confidence'  => 0.85,
                        'file'        => $file,
                        'line'        => $i + 1,
                    ];
                }
                continue;
            }

            // -----------------------------------------------------------------
            // Unchecked subprocess — bare statement
            // -----------------------------------------------------------------
            if ($this->isUncheckedSubprocess($raw)) {
                $findings[] = [
                    'nodeId'      => $this->nodeId($module, $funcName),
                    'type'        => Bug::TYPE_PYTHON_UNCHECKED_SUBPROCESS,
                    'description' => 'Subprocess call result is not captured (unchecked exit code)',
                    'confidence'  => 0.75,
                    'file'        => $file,
                    'line'        => $i + 1,
                ];
            }
        }

        return $findings;
    }

    // -------------------------------------------------------------------------
    // Pattern helpers
    // -------------------------------------------------------------------------

    private function nodeId(string $module, string $function): string
    {
        return $module . '::' . $function;
    }

    /**
     * Returns true when the `def` line contains a mutable default argument
     * (`= [` or `= {` inside the parameter list).
     */
    private function hasMutableDefaultArg(string $defLine): bool
    {
        // Match `= [` or `= {` anywhere after the opening parenthesis
        return (bool) preg_match('/\(.*=\s*[\[\{]/', $defLine);
    }

    /**
     * Returns true when the `def` line declares a public, non-dunder function
     * that has no `->` return type annotation.
     */
    private function isMissingReturnType(string $defLine, string $funcName): bool
    {
        // Exclude private (_name) and dunder (__name__) functions
        if (str_starts_with($funcName, '_')) {
            return false;
        }

        return !str_contains($defLine, '->');
    }

    /**
     * Looks ahead from the `except` line to determine whether the block body
     * contains only trivial statements (pass, comments, ellipsis).
     *
     * @param string[] $lines
     */
    private function isExceptionSwallowing(array $lines, int $exceptIndex): bool
    {
        $exceptLine   = $lines[$exceptIndex];
        $exceptIndent = strlen($exceptLine) - strlen(ltrim($exceptLine));
        $total        = count($lines);

        for ($j = $exceptIndex + 1; $j < $total; $j++) {
            $line    = $lines[$j];
            $trimmed = trim($line);

            if ($trimmed === '') {
                continue;
            }

            $indent = strlen($line) - strlen(ltrim($line));

            // Back at or before the except indentation — block ended
            if ($indent <= $exceptIndent) {
                break;
            }

            // Non-trivial statement found → NOT swallowing
            if ($trimmed !== 'pass' && $trimmed !== '...' && !str_starts_with($trimmed, '#')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns true when the line is a bare subprocess call (not an assignment).
     *
     * Matches:
     *   os.system(...)
     *   subprocess.run(...)
     *   subprocess.call(...)
     *   subprocess.Popen(...)
     */
    private function isUncheckedSubprocess(string $line): bool
    {
        // Must not start with an identifier followed by `=` (assignment)
        if (preg_match('/^\s*\w+\s*=\s*(?:os\.|subprocess\.)/', $line)) {
            return false;
        }

        return (bool) preg_match(
            '/^\s*(?:os\.system\s*\(|subprocess\.(?:run|call|Popen)\s*\()/',
            $line
        );
    }
}
