<?php

declare(strict_types=1);

namespace FlowEngine\Infrastructure\Analyzer\Visitors;

use FlowEngine\Application\DTO\SymbolDTO;
use PhpParser\Node;

/**
 * Collects top-level PHP function and const declarations as SymbolDTOs.
 *
 * Does NOT enter class bodies — class members are already captured as flow nodes.
 * Only emits symbols for declarations at the file (namespace) scope.
 *
 * @internal
 */
final class TopLevelIdentifierVisitor implements NodeVisitor
{
    /**
     * @internal
     */
    public function supports(Node $node): bool
    {
        return $node instanceof Node\Stmt\Function_
            || $node instanceof Node\Stmt\Const_
            || $node instanceof Node\Expr\FuncCall;
    }

    /**
     * @internal
     */
    public function enter(Node $node, VisitorContext $context): void
    {
        // Skip anything inside a class body — class members are flow nodes, not symbols.
        if ($context->isInsideClass()) {
            return;
        }

        if ($node instanceof Node\Stmt\Function_) {
            $name = $node->name->toString();
            $line = $node->getStartLine();
            if ($line >= 1) {
                $context->addSymbol(SymbolDTO::make($name, 'function', $context->file(), $line));
            }
            return;
        }

        if ($node instanceof Node\Stmt\Const_) {
            foreach ($node->consts as $const) {
                $name = $const->name->toString();
                $line = $const->getStartLine();
                if ($line >= 1) {
                    $context->addSymbol(SymbolDTO::make($name, 'const', $context->file(), $line));
                }
            }
        }
    }

    /**
     * @internal
     */
    public function leave(Node $node, VisitorContext $context): void
    {
        // Nothing to clean up
    }
}
