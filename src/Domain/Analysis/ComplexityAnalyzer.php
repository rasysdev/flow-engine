<?php

namespace FlowEngine\Domain\Analysis;

use FlowEngine\Domain\Contracts\Flow;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

/**
 * Analisa complexidade ciclomática de métodos.
 * 
 * Cyclomatic Complexity = 1 + número de decision points
 * 
 * Decision points:
 * - if, elseif, else
 * - for, foreach, while, do-while
 * - switch, case
 * - catch
 * - ternary (? :)
 * - logical operators (&& e ||)
 * 
 * Thresholds (McCabe):
 * - 1-10: Simple (LOW)
 * - 11-20: Moderate (MEDIUM)
 * - 21-50: Complex (HIGH)
 * - 51+: Very Complex (CRITICAL)
 */
final class ComplexityAnalyzer
{
    public function __construct(
        private Flow $flow
    ) {
    }

    /**
     * Calcula complexidade de um método específico.
     * 
     * @param string $file Caminho do arquivo
     * @param string $className Nome da classe
     * @param string $methodName Nome do método
     * @return int Complexidade ciclomática
     */
    public function calculateMethodComplexity(
        string $file,
        string $className,
        string $methodName
    ): int {
        $code = file_get_contents($file);

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        if (!$ast) {
            return 1; // Complexidade base
        }

        // Encontrar o método específico
        $nodeFinder = new NodeFinder();
        $methods = $nodeFinder->findInstanceOf($ast, Node\Stmt\ClassMethod::class);

        foreach ($methods as $method) {
            if ($method->name->toString() === $methodName) {
                return $this->calculateComplexity($method);
            }
        }

        return 1;
    }

    /**
     * Calcula complexidade de um node AST.
     */
    private function calculateComplexity(Node $node): int
    {
        $complexity = 1; // Base complexity

        $nodeFinder = new NodeFinder();

        // 1. if, elseif, else
        $complexity += count($nodeFinder->findInstanceOf($node, Node\Stmt\If_::class));
        $complexity += count($nodeFinder->findInstanceOf($node, Node\Stmt\ElseIf_::class));

        // 2. Loops
        $complexity += count($nodeFinder->findInstanceOf($node, Node\Stmt\For_::class));
        $complexity += count($nodeFinder->findInstanceOf($node, Node\Stmt\Foreach_::class));
        $complexity += count($nodeFinder->findInstanceOf($node, Node\Stmt\While_::class));
        $complexity += count($nodeFinder->findInstanceOf($node, Node\Stmt\Do_::class));

        // 3. Switch cases
        $switches = $nodeFinder->findInstanceOf($node, Node\Stmt\Switch_::class);
        foreach ($switches as $switch) {
            $complexity += count($switch->cases);
        }

        // 4. Catch blocks
        $tries = $nodeFinder->findInstanceOf($node, Node\Stmt\TryCatch::class);
        foreach ($tries as $try) {
            $complexity += count($try->catches);
        }

        // 5. Ternary operators
        $complexity += count($nodeFinder->findInstanceOf($node, Node\Expr\Ternary::class));

        // 6. Logical operators (&& e ||)
        $booleanAnd = $nodeFinder->findInstanceOf($node, Node\Expr\BinaryOp\BooleanAnd::class);
        $booleanOr = $nodeFinder->findInstanceOf($node, Node\Expr\BinaryOp\BooleanOr::class);
        $complexity += count($booleanAnd) + count($booleanOr);

        // 7. Null coalesce (??)
        $complexity += count($nodeFinder->findInstanceOf($node, Node\Expr\BinaryOp\Coalesce::class));

        return $complexity;
    }

    /**
     * Determina nível de complexidade.
     * 
     * Baseado em thresholds de McCabe.
     * 
     * @internal 
     */
    public static function getComplexityLevel(int $complexity): string
    {
        if ($complexity >= 51) {
            return 'CRITICAL';
        }

        if ($complexity >= 21) {
            return 'HIGH';
        }

        if ($complexity >= 11) {
            return 'MEDIUM';
        }

        return 'LOW';
    }

    /**
     * Retorna todos os métodos com sua complexidade.
     * 
     * @return array<string, array{complexity: int, level: string}>
     */
    public function analyzeAll(): array
    {
        $results = [];

        foreach ($this->flow->nodes() as $node) {
            $complexity = $this->calculateMethodComplexity(
                $node->file(),
                $node->class(),
                $node->method()
            );

            $results[$node->id()] = [
                'complexity' => $complexity,
                'level' => self::getComplexityLevel($complexity),
                'file' => $node->file(),
                'line' => $node->line(),
            ];
        }

        return $results;
    }

    /**
     * Retorna apenas métodos complexos (HIGH ou CRITICAL).
     * 
     * @api
     * @return array<string, array{complexity: int, level: string}>
     */
    public function findComplexMethods(): array
    {
        $all = $this->analyzeAll();

        return array_filter($all, function ($data) {
            return in_array($data['level'], ['HIGH', 'CRITICAL']);
        });
    }

    /**
     * Estatísticas gerais de complexidade.
     * 
     * @api
     */
    public function stats(): array
    {
        $all = $this->analyzeAll();

        $complexities = array_column($all, 'complexity');

        $byLevel = [
            'LOW' => 0,
            'MEDIUM' => 0,
            'HIGH' => 0,
            'CRITICAL' => 0,
        ];

        foreach ($all as $data) {
            $byLevel[$data['level']]++;
        }

        return [
            'total' => count($all),
            'avgComplexity' => count($complexities) > 0
                ? round(array_sum($complexities) / count($complexities), 2)
                : 0,
            'maxComplexity' => count($complexities) > 0 ? max($complexities) : 0,
            'minComplexity' => count($complexities) > 0 ? min($complexities) : 0,
            'byLevel' => $byLevel,
        ];
    }
}