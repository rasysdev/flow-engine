<?php

namespace FlowEngine\Infrastructure\Analyzer\Visitors;

use PhpParser\Node;

/**
 * Detecta instanciação de classes: new Class()
 * 
 * NOVO em v3.1
 * 
 * Exemplos:
 * - new User()
 * - new Service($dep)
 * - new static()
 * - new self()
 * 
 * Formato da edge:
 * - From: CurrentClass::currentMethod
 * - To: InstantiatedClass::__construct
 * - Type: instantiation
 * 
 * Casos não suportados:
 * - new $className() (classe dinâmica)
 * - new class { } (classe anônima)
 * 
 * @internal
 */
final class NewInstanceVisitor implements NodeVisitor
{
    /**
     * @internal
     */
    public function supports(Node $node): bool
    {
        return $node instanceof Node\Expr\New_;
    }

    /**
     * @internal
     */
    public function enter(Node $node, VisitorContext $context): void
    {
        /** @var Node\Expr\New_ $node */
        
        if (!$context->isInsideMethod()) {
            return;
        }

        // Ignorar classes anônimas
        if ($node->class instanceof Node\Stmt\Class_) {
            return; // new class { } - não rastrear
        }

        // Ignorar instanciação dinâmica
        if (!$node->class instanceof Node\Name) {
            return; // new $className() - não podemos inferir
        }

        $className = $node->class->toString();

        // Resolver self/static
        if ($className === 'self' || $className === 'static') {
            $className = $context->currentClass();
        } elseif ($className === 'parent') {
            // Limitação: não sabemos a classe pai
            return;
        } else {
            // Resolver FQN
            $className = $context->resolveFQN($className);
        }

        // Criar edge de instanciação
        // Formato: CurrentClass::currentMethod -> ClassName::__construct
        $fromId = $context->currentNodeId();
        $toId = $className . '::__construct';

        $context->addEdge(new \FlowEngine\Domain\Flow\Edge(
            $fromId,
            $toId,
            '__construct',
            'instantiation'
        ));
    }

    /**
     * @internal
     */
    public function leave(Node $node, VisitorContext $context): void
    {
        // Nada a fazer
    }
}
