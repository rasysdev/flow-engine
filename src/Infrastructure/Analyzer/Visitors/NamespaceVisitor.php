<?php

namespace FlowEngine\Infrastructure\Analyzer\Visitors;

use PhpParser\Node;


/**
 * Captura declarações de namespace.
 * 
 * Exemplo:
 *   namespace App\Services;
 * 
 * Atualiza: VisitorContext::currentNamespace
 * 
 * @internal
 */
final class NamespaceVisitor implements NodeVisitor
{
    /**
     * @internal
     */
    public function supports(Node $node): bool
    {
        return $node instanceof Node\Stmt\Namespace_;
    }

    /**
     * @internal
     */
    public function enter(Node $node, VisitorContext $context): void
    {
        /** @var Node\Stmt\Namespace_ $node */
        $namespace = $node->name?->toString();
        
        $context->setNamespace($namespace);
    }

    /**
     * @internal
     */
    public function leave(Node $node, VisitorContext $context): void
    {
        // Não precisa limpar - namespace persiste até próximo arquivo
    }
}
