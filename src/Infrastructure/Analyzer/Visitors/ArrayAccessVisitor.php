<?php

namespace FlowEngine\Infrastructure\Analyzer\Visitors;

use PhpParser\Node;

/**
 * Detecta acesso a arrays e ArrayAccess: $arr[$key]
 * 
 * NOVO em v3.1
 * 
 * Exemplos:
 * - $config['database']
 * - $this->data[$key]
 * - $obj[$offset] (ArrayAccess)
 * 
 * Formato da edge:
 * - From: CurrentClass::currentMethod
 * - To: ArrayClass::[$key] (se puder inferir)
 * - Type: array_access
 * 
 * Limitações:
 * - Não rastreia arrays simples ($arr = []; $arr[0])
 * - Foca em acesso a propriedades que são arrays
 * - Não detecta chaves dinâmicas $$var[$key]
 * 
 * @internal
 */
final class ArrayAccessVisitor implements NodeVisitor
{
    /**
     * @internal
     */
    public function supports(Node $node): bool
    {
        return $node instanceof Node\Expr\ArrayDimFetch;
    }

    /**
     * @internal
     */
    public function enter(Node $node, VisitorContext $context): void
    {
        /** @var Node\Expr\ArrayDimFetch $node */
        
        if (!$context->isInsideMethod()) {
            return;
        }

        // Tentar inferir a variável sendo acessada
        $var = $node->var;

        // Caso 1: $this->property[$key]
        if ($var instanceof Node\Expr\PropertyFetch) {
            $this->handlePropertyArrayAccess($var, $node, $context);
            return;
        }

        // Caso 2: $this->method()[$key]
        if ($var instanceof Node\Expr\MethodCall) {
            $this->handleMethodArrayAccess($var, $node, $context);
            return;
        }

        // Caso 3: Class::$property[$key]
        if ($var instanceof Node\Expr\StaticPropertyFetch) {
            $this->handleStaticPropertyArrayAccess($var, $node, $context);
            return;
        }

        // Caso 4: Variáveis simples ($arr[$key]) - ignorar por ora
        // Precisaria type inference para rastrear
    }

    /**
     * @internal
     */
    public function leave(Node $node, VisitorContext $context): void
    {
        // Nada a fazer
    }

    /**
     * Trata acesso a array em propriedade: $this->data[$key]
     * 
     * @internal
     */
    private function handlePropertyArrayAccess(
        Node\Expr\PropertyFetch $property,
        Node\Expr\ArrayDimFetch $arrayAccess,
        VisitorContext $context
    ): void {
        if (!$property->name instanceof Node\Identifier) {
            return;
        }

        $propertyName = $property->name->toString();

        // Inferir classe da property
        $propertyClass = $this->inferClass($property->var, $context);

        if (!$propertyClass) {
            return;
        }

        // Extrair chave (se for string literal)
        $key = $this->extractKey($arrayAccess->dim);

        // Criar edge
        $fromId = $context->currentNodeId();
        $toId = $propertyClass . '::$' . $propertyName . '[' . $key . ']';

        $context->addEdge(new \FlowEngine\Domain\Flow\Edge(
            $fromId,
            $toId,
            '$' . $propertyName . '[' . $key . ']',
            'array_access'
        ));
    }

    /**
     * Trata acesso a array em método: $this->getData()[$key]
     * 
     * @internal
     */
    private function handleMethodArrayAccess(
        Node\Expr\MethodCall $method,
        Node\Expr\ArrayDimFetch $arrayAccess,
        VisitorContext $context
    ): void {
        if (!$method->name instanceof Node\Identifier) {
            return;
        }

        $methodName = $method->name->toString();
        $methodClass = $this->inferClass($method->var, $context);

        if (!$methodClass) {
            return;
        }

        $key = $this->extractKey($arrayAccess->dim);

        // Criar edge
        $fromId = $context->currentNodeId();
        $toId = $methodClass . '::' . $methodName . '[' . $key . ']';

        $context->addEdge(new \FlowEngine\Domain\Flow\Edge(
            $fromId,
            $toId,
            $methodName . '[' . $key . ']',
            'array_access'
        ));
    }

    /**
     * Trata acesso a array em propriedade estática: Class::$data[$key]
     * 
     * @internal
     */
    private function handleStaticPropertyArrayAccess(
        Node\Expr\StaticPropertyFetch $property,
        Node\Expr\ArrayDimFetch $arrayAccess,
        VisitorContext $context
    ): void {
        if (!$property->name instanceof Node\VarLikeIdentifier) {
            return;
        }

        if (!$property->class instanceof Node\Name) {
            return;
        }

        $propertyName = $property->name->toString();
        $className = $context->resolveFQN($property->class->toString());
        $key = $this->extractKey($arrayAccess->dim);

        // Criar edge
        $fromId = $context->currentNodeId();
        $toId = $className . '::$' . $propertyName . '[' . $key . ']';

        $context->addEdge(new \FlowEngine\Domain\Flow\Edge(
            $fromId,
            $toId,
            '$' . $propertyName . '[' . $key . ']',
            'array_access'
        ));
    }

    /**
     * Extrai chave do array (se possível).
     * 
     * @internal
     */
    private function extractKey(?Node\Expr $dim): string
    {
        if ($dim === null) {
            return '*'; // $arr[] - append
        }

        // String literal
        if ($dim instanceof Node\Scalar\String_) {
            return $dim->value;
        }

        // Número
        if ($dim instanceof Node\Scalar\Int_) {
            return (string)$dim->value;
        }

        // Constante
        if ($dim instanceof Node\Expr\ConstFetch) {
            return $dim->name->toString();
        }

        // Dinâmico - não podemos determinar
        return '?';
    }

    /**
     * Inferir classe de uma expressão (mesmo que MethodCallVisitor).
     * 
     * @internal
     */
    private function inferClass(Node\Expr $expr, VisitorContext $context): ?string
    {
        if ($expr instanceof Node\Expr\Variable && $expr->name === 'this') {
            return $context->currentClass();
        }

        if ($expr instanceof Node\Expr\New_) {
            if ($expr->class instanceof Node\Name) {
                return $context->resolveFQN($expr->class->toString());
            }
        }

        if ($expr instanceof Node\Expr\MethodCall) {
            return $this->inferClass($expr->var, $context);
        }

        if ($expr instanceof Node\Expr\PropertyFetch) {
            if ($expr->var instanceof Node\Expr\Variable && $expr->var->name === 'this') {
                return $context->currentClass();
            }
            return $this->inferClass($expr->var, $context);
        }

        return null;
    }
}
