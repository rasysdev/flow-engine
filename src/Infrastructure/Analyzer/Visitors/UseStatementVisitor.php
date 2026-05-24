<?php

namespace FlowEngine\Infrastructure\Analyzer\Visitors;

use FlowEngine\Application\DTO\SymbolDTO;
use PhpParser\Node;

/**
 * Collects PHP `use` import statements and registers alias → FQN mappings
 * in the VisitorContext so that other visitors can resolve short class names.
 *
 * Must be registered BEFORE any visitor that calls VisitorContext::resolveClass().
 *
 * @internal
 */
final class UseStatementVisitor implements NodeVisitor
{
    /**
     * @internal
     */
    public function supports(Node $node): bool
    {
        return $node instanceof Node\Stmt\Use_ || $node instanceof Node\Stmt\GroupUse;
    }

    /**
     * @internal
     */
    public function enter(Node $node, VisitorContext $context): void
    {
        if ($node instanceof Node\Stmt\Use_) {
            foreach ($node->uses as $use) {
                $fqn = $use->name->toString();
                $alias = $use->alias !== null
                    ? $use->alias->toString()
                    : $use->name->getLast();
                $context->addUse($alias, $fqn);
                $line = $use->getStartLine();
                if ($line >= 1) {
                    $context->addSymbol(SymbolDTO::make($alias, 'import', $context->file(), $line, $fqn));
                }
            }
            return;
        }

        /** @var Node\Stmt\GroupUse $node */
        $prefix = rtrim($node->prefix->toString(), '\\');
        foreach ($node->uses as $use) {
            $suffix = ltrim($use->name->toString(), '\\');
            $fqn = $prefix !== '' ? $prefix . '\\' . $suffix : $suffix;
            $alias = $use->alias !== null
                ? $use->alias->toString()
                : $use->name->getLast();
            $context->addUse($alias, $fqn);
            $line = $use->getStartLine();
            if ($line >= 1) {
                $context->addSymbol(SymbolDTO::make($alias, 'import', $context->file(), $line, $fqn));
            }
        }
    }

    /**
     * @internal
     */
    public function leave(Node $node, VisitorContext $context): void
    {
        // Nothing to clean up — aliases persist for the whole file
    }
}
