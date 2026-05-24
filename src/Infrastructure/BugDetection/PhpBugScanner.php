<?php

namespace FlowEngine\Infrastructure\BugDetection;

use FlowEngine\Domain\Analysis\Bug;
use FlowEngine\Domain\Contracts\BugScannerPort;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\Node\Expr;

/**
 * Scans PHP files for common bug patterns using nikic/php-parser.
 *
 * Detected patterns:
 * - exception_swallowing : empty catch or catch with only `return null`
 * - resource_leak        : fopen/curl_init without matching fclose/curl_close
 * - missing_return_type  : public method with no declared return type
 *
 * This traversal is standalone and does NOT touch the existing AstParser/visitor chain.
 */
final class PhpBugScanner implements BugScannerPort
{
    /** @param string[] $files Absolute PHP file paths */
    public function __construct(private array $files) {}

    /**
     * @return array<int, array{nodeId:string, type:string, description:string, confidence:float, file:string, line:int|null}>
     */
    public function scan(): array
    {
        $parser    = (new ParserFactory())->createForNewestSupportedVersion();
        $findings  = [];

        foreach ($this->files as $file) {
            $code = @file_get_contents($file);
            if ($code === false || trim($code) === '') {
                continue;
            }

            try {
                $ast = $parser->parse($code);
            } catch (\Throwable) {
                continue;
            }

            if ($ast === null) {
                continue;
            }

            $collector = new BugPatternCollector($file);
            $traverser = new NodeTraverser();
            $traverser->addVisitor($collector);
            $traverser->traverse($ast);

            foreach ($collector->findings() as $finding) {
                $findings[] = $finding;
            }
        }

        return $findings;
    }
}

/**
 * @internal
 */
final class BugPatternCollector extends NodeVisitorAbstract
{
    private string $currentNamespace = '';
    private string $currentClass     = '';
    private string $currentMethod    = '';
    private ?Node $currentReturnType = null;

    /** @var array<int, array{nodeId:string, type:string, description:string, confidence:float, file:string, line:int|null}> */
    private array $findings = [];

    public function __construct(private string $file) {}

    public function enterNode(Node $node): null
    {
        if ($node instanceof Stmt\Namespace_) {
            $this->currentNamespace = $node->name !== null ? $node->name->toString() : '';
        }

        if ($node instanceof Stmt\Class_) {
            $this->currentClass = $node->name !== null ? $node->name->toString() : '';
        }

        if ($node instanceof Stmt\ClassMethod) {
            $this->currentMethod = $node->name->toString();
            $this->currentReturnType = $node->returnType;
            $this->checkMissingReturnType($node);
        }

        if ($node instanceof Stmt\TryCatch) {
            $this->checkExceptionSwallowing($node);
        }

        return null;
    }

    public function leaveNode(Node $node): null
    {
        if ($node instanceof Stmt\ClassMethod) {
            $this->checkResourceLeak($node);
            $this->checkNullDereference($node);
            $this->checkHardcodedCredentials($node);
            $this->currentMethod = '';
            $this->currentReturnType = null;
        }

        if ($node instanceof Stmt\Class_) {
            $this->currentClass = '';
        }

        if ($node instanceof Stmt\Namespace_) {
            $this->currentNamespace = '';
        }

        return null;
    }

    /** @return array<int, array{nodeId:string, type:string, description:string, confidence:float, file:string, line:int|null}> */
    public function findings(): array
    {
        return $this->findings;
    }

    private function nodeId(): string
    {
        $ns = $this->currentNamespace !== '' ? $this->currentNamespace . '\\' : '';
        return $ns . $this->currentClass . '::' . $this->currentMethod;
    }

    private function checkMissingReturnType(Stmt\ClassMethod $node): void
    {
        if ($this->currentClass === '') {
            return;
        }

        $name = $node->name->toString();
        if ($name === '__construct' || $name === '__destruct') {
            return;
        }

        if (!($node->flags & Stmt\Class_::MODIFIER_PUBLIC)) {
            return;
        }

        if ($node->returnType !== null) {
            return;
        }

        $this->findings[] = [
            'nodeId'      => $this->nodeId(),
            'type'        => 'missing_return_type',
            'description' => "Public method '{$name}' has no declared return type",
            'confidence'  => 0.5,
            'file'        => $this->file,
            'line'        => $node->getStartLine() ?: null,
        ];
    }

    private function checkExceptionSwallowing(Stmt\TryCatch $node): void
    {
        if ($this->currentClass === '' || $this->currentMethod === '') {
            return;
        }

        foreach ($node->catches as $catch) {
            if ($this->isCatchSwallowing($catch)) {
                $this->findings[] = [
                    'nodeId'      => $this->nodeId(),
                    'type'        => 'exception_swallowing',
                    'description' => 'Caught exception is silently swallowed (empty catch or only `return null`)',
                    'confidence'  => 0.9,
                    'file'        => $this->file,
                    'line'        => $catch->getStartLine() ?: null,
                ];
            }
        }
    }

    private function isCatchSwallowing(Stmt\Catch_ $catch): bool
    {
        // Comments in PHP-Parser become Stmt\Nop — ignore them when evaluating emptiness
        $meaningful = array_values(
            array_filter($catch->stmts, fn(Node $s) => !($s instanceof Stmt\Nop))
        );

        if ($meaningful === []) {
            return true;
        }

        if (count($meaningful) === 1) {
            $stmt = $meaningful[0];
            if ($stmt instanceof Stmt\Return_) {
                if ($stmt->expr === null) {
                    return true;
                }

                if ($this->isIntentionalTypedFallback($stmt->expr)) {
                    return false;
                }

                if ($this->isNullLiteral($stmt->expr)) {
                    return true;
                }

                return true;
            }
        }

        return false;
    }

    private function isNullLiteral(Node $expr): bool
    {
        return $expr instanceof Expr\ConstFetch
            && strtolower($expr->name->toString()) === 'null';
    }

    private function isIntentionalTypedFallback(Node $expr): bool
    {
        if ($this->currentReturnType === null) {
            return false;
        }

        $declaredTypes = $this->declaredReturnTypes($this->currentReturnType);
        if ($declaredTypes === []) {
            return false;
        }

        if ($this->isNullLiteral($expr)) {
            return in_array('null', $declaredTypes, true);
        }

        return match (true) {
            $expr instanceof Node\Scalar\String_ => in_array('string', $declaredTypes, true),
            $expr instanceof Node\Scalar\LNumber => in_array('int', $declaredTypes, true)
                || in_array('float', $declaredTypes, true),
            $expr instanceof Node\Scalar\DNumber => in_array('float', $declaredTypes, true),
            $expr instanceof Expr\Array_ => in_array('array', $declaredTypes, true),
            $expr instanceof Expr\ConstFetch => $this->isBooleanLiteral($expr)
                && in_array('bool', $declaredTypes, true),
            default => false,
        };
    }

    /**
     * @return string[]
     */
    private function declaredReturnTypes(Node $returnType): array
    {
        if ($returnType instanceof Node\NullableType) {
            return array_values(array_unique([
                ...$this->declaredReturnTypes($returnType->type),
                'null',
            ]));
        }

        if ($returnType instanceof Node\UnionType) {
            $types = [];
            foreach ($returnType->types as $type) {
                $types = [...$types, ...$this->declaredReturnTypes($type)];
            }

            return array_values(array_unique($types));
        }

        if ($returnType instanceof Node\IntersectionType) {
            return [];
        }

        if ($returnType instanceof Node\Identifier || $returnType instanceof Node\Name) {
            return [strtolower($returnType->toString())];
        }

        return [];
    }

    private function isBooleanLiteral(Expr\ConstFetch $expr): bool
    {
        return in_array(strtolower($expr->name->toString()), ['true', 'false'], true);
    }

    private function checkResourceLeak(Stmt\ClassMethod $node): void
    {
        if ($this->currentClass === '' || $this->currentMethod === '') {
            return;
        }

        $openers = ['fopen', 'curl_init'];
        $closers = ['fclose', 'curl_close'];

        $openCount  = 0;
        $closeCount = 0;

        $this->walkStmtsForFunctionCalls(
            $node->stmts ?? [],
            $openers,
            $closers,
            $openCount,
            $closeCount
        );

        if ($openCount > $closeCount) {
            $this->findings[] = [
                'nodeId'      => $this->nodeId(),
                'type'        => 'resource_leak',
                'description' => "Method opens {$openCount} resource(s) but closes only {$closeCount}",
                'confidence'  => 0.8,
                'file'        => $this->file,
                'line'        => $node->getStartLine() ?: null,
            ];
        }
    }

    /**
     * @param Stmt[] $stmts
     * @param string[] $openers
     * @param string[] $closers
     */
    private function walkStmtsForFunctionCalls(
        array $stmts,
        array $openers,
        array $closers,
        int &$openCount,
        int &$closeCount
    ): void {
        foreach ($stmts as $stmt) {
            $this->walkNodeForFunctionCalls($stmt, $openers, $closers, $openCount, $closeCount);
        }
    }

    private function walkNodeForFunctionCalls(
        Node $node,
        array $openers,
        array $closers,
        int &$openCount,
        int &$closeCount
    ): void {
        if ($node instanceof Expr\FuncCall && $node->name instanceof Node\Name) {
            $name = strtolower($node->name->toString());
            if (in_array($name, $openers, true)) {
                $openCount++;
            } elseif (in_array($name, $closers, true)) {
                $closeCount++;
            }
        }

        foreach ($node->getSubNodeNames() as $sub) {
            $child = $node->$sub;
            if ($child instanceof Node) {
                $this->walkNodeForFunctionCalls($child, $openers, $closers, $openCount, $closeCount);
            } elseif (is_array($child)) {
                foreach ($child as $item) {
                    if ($item instanceof Node) {
                        $this->walkNodeForFunctionCalls($item, $openers, $closers, $openCount, $closeCount);
                    }
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // Null Dereference Detection
    // -------------------------------------------------------------------------

    private function checkNullDereference(Stmt\ClassMethod $node): void
    {
        if ($this->currentClass === '' || $this->currentMethod === '') {
            return;
        }

        /** @var array<string, int> varName => assignment line */
        $possiblyNull = [];

        foreach ($node->stmts ?? [] as $stmt) {
            // A null-checking `if` clears the risk for tested variables
            if ($stmt instanceof Stmt\If_) {
                $this->clearNullCheckedVars($stmt->cond, $possiblyNull);
            }

            // Assignment from a method/function call → variable is possibly null
            if ($stmt instanceof Stmt\Expression && $stmt->expr instanceof Expr\Assign) {
                $assign = $stmt->expr;
                if ($assign->var instanceof Expr\Variable && is_string($assign->var->name)) {
                    $varName = $assign->var->name;
                    $rhs     = $assign->expr;
                    if (($rhs instanceof Expr\MethodCall
                        || $rhs instanceof Expr\StaticCall
                        || $rhs instanceof Expr\FuncCall)
                        && !$this->isMustCall($rhs)
                    ) {
                        $possiblyNull[$varName] = $stmt->getStartLine();
                    } else {
                        // Reassigned to something else (or a must* assertive call that throws
                        // on failure instead of returning null/false) — no longer risky
                        unset($possiblyNull[$varName]);
                    }
                }
                continue;
            }

            // Direct dereference of a possibly-null variable (non-nullsafe)
            if ($stmt instanceof Stmt\Expression) {
                $expr = $stmt->expr;
                if ($expr instanceof Expr\MethodCall
                    && $expr->var instanceof Expr\Variable
                    && is_string($expr->var->name)
                    && isset($possiblyNull[$expr->var->name])
                ) {
                    $varName = $expr->var->name;
                    $this->findings[] = [
                        'nodeId'      => $this->nodeId(),
                        'type'        => Bug::TYPE_NULL_DEREFERENCE,
                        'description' => "Variable \${$varName} is assigned from a call that may return null and is dereferenced without a null check",
                        'confidence'  => 0.8,
                        'file'        => $this->file,
                        'line'        => $stmt->getStartLine() ?: null,
                    ];
                    // Report once per variable per method
                    unset($possiblyNull[$varName]);
                }
            }
        }
    }

    /**
     * Returns true for method calls whose name starts with `must`.
     *
     * By convention, `must*` methods throw on failure rather than returning
     * null/false, so assigning their result to a variable is safe to dereference
     * without an additional null check.
     */
    private function isMustCall(Expr $expr): bool
    {
        return $expr instanceof Expr\MethodCall
            && $expr->name instanceof Node\Identifier
            && str_starts_with($expr->name->toString(), 'must');
    }

    /**
     * Remove variables from the possibly-null set when they are guarded by a condition.
     *
     * Handles: `if ($var)`, `if ($var !== null)`, `if (isset($var))`, boolean `&&`.
     *
     * @param array<string, int> $possiblyNull
     */
    private function clearNullCheckedVars(Expr $condition, array &$possiblyNull): void
    {
        // if ($var)
        if ($condition instanceof Expr\Variable && is_string($condition->name)) {
            unset($possiblyNull[$condition->name]);
            return;
        }

        // if (isset($var))
        if ($condition instanceof Expr\Isset_) {
            foreach ($condition->vars as $var) {
                if ($var instanceof Expr\Variable && is_string($var->name)) {
                    unset($possiblyNull[$var->name]);
                }
            }
            return;
        }

        // if ($var !== null) or if (null !== $var)
        if ($condition instanceof Expr\BinaryOp\NotIdentical
            || $condition instanceof Expr\BinaryOp\NotEqual
        ) {
            $left  = $condition->left;
            $right = $condition->right;
            $null  = fn (Node $n): bool => $n instanceof Expr\ConstFetch
                && strtolower($n->name->toString()) === 'null';

            if ($left instanceof Expr\Variable && is_string($left->name) && $null($right)) {
                unset($possiblyNull[$left->name]);
            } elseif ($right instanceof Expr\Variable && is_string($right->name) && $null($left)) {
                unset($possiblyNull[$right->name]);
            }
            return;
        }

        // if ($a !== null && $b !== null) — recurse on both sides
        if ($condition instanceof Expr\BinaryOp\BooleanAnd
            || $condition instanceof Expr\BinaryOp\LogicalAnd
        ) {
            $this->clearNullCheckedVars($condition->left, $possiblyNull);
            $this->clearNullCheckedVars($condition->right, $possiblyNull);
        }
    }

    // -------------------------------------------------------------------------
    // Hardcoded Credential Detection
    // -------------------------------------------------------------------------

    private function checkHardcodedCredentials(Stmt\ClassMethod $node): void
    {
        if ($this->currentClass === '' || $this->currentMethod === '') {
            return;
        }

        foreach ($node->stmts ?? [] as $stmt) {
            $this->walkNodeForCredentialAssigns($stmt);
        }
    }

    private function walkNodeForCredentialAssigns(Node $node): void
    {
        // Check Assign nodes: $credentialVar = 'literal' or $anyVar = 'known-pattern'
        if ($node instanceof Expr\Assign
            && $node->var instanceof Expr\Variable
            && is_string($node->var->name)
            && $node->expr instanceof Node\Scalar\String_
        ) {
            $varName  = $node->var->name;
            $value    = $node->expr->value;
            $isCredVar = (bool) preg_match(
                '/password|secret|key|token|api_key|apikey|passwd|pwd|credential/i',
                $varName
            );
            $isKnown = $this->matchesKnownCredentialPattern($value);

            if ($isKnown) {
                $this->findings[] = [
                    'nodeId'      => $this->nodeId(),
                    'type'        => Bug::TYPE_HARDCODED_CREDENTIAL,
                    'description' => "Hardcoded API key or token detected in variable '\${$varName}'",
                    'confidence'  => 0.95,
                    'file'        => $this->file,
                    'line'        => $node->getStartLine() ?: null,
                ];
                // Don't double-report via entropy check
                return;
            }

            if ($isCredVar && $this->isHighEntropy($value, 4.5)) {
                $this->findings[] = [
                    'nodeId'      => $this->nodeId(),
                    'type'        => Bug::TYPE_HARDCODED_CREDENTIAL,
                    'description' => "High-entropy string literal assigned to credential variable '\${$varName}'",
                    'confidence'  => 0.8,
                    'file'        => $this->file,
                    'line'        => $node->getStartLine() ?: null,
                ];
                return;
            }

            // Skip recursing into children of this Assign to avoid double-reporting
            return;
        }

        // Recurse into all other node types
        foreach ($node->getSubNodeNames() as $sub) {
            $child = $node->$sub;
            if ($child instanceof Node) {
                $this->walkNodeForCredentialAssigns($child);
            } elseif (is_array($child)) {
                foreach ($child as $item) {
                    if ($item instanceof Node) {
                        $this->walkNodeForCredentialAssigns($item);
                    }
                }
            }
        }
    }

    private function matchesKnownCredentialPattern(string $value): bool
    {
        $patterns = [
            '/^(AKIA|ASIA)[A-Z0-9]{16}/',  // AWS access key
            '/^sk-ant-[a-zA-Z0-9]/',        // Anthropic API key
            '/^sk-[a-zA-Z0-9]{48}/',        // OpenAI API key
            '/^ghp_[a-zA-Z0-9]{36}/',       // GitHub Personal Access Token
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    private function isHighEntropy(string $value, float $threshold): bool
    {
        $len = strlen($value);
        if ($len < 16) {
            return false;
        }

        $freq    = array_count_values(str_split($value));
        $entropy = 0.0;

        foreach ($freq as $count) {
            $p       = $count / $len;
            $entropy -= $p * log($p, 2);
        }

        return $entropy > $threshold;
    }
}
