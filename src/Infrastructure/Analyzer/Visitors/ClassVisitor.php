<?php

namespace FlowEngine\Infrastructure\Analyzer\Visitors;

use PhpParser\Node;

/**
 * Captura declarações de classe e detecta traits/interfaces.
 * 
 * Responsabilidades:
 * - Capturar nome da classe
 * - Resolver FQN (namespace + classe)
 * - Detectar traits usados
 * - Detectar interfaces implementadas
 * 
 * Exemplo:
 *   class UserService implements ServiceInterface {
 *       use LoggerTrait;
 *   }
 * 
 * Atualiza:
 * - VisitorContext::currentClass
 * - VisitorContext::currentTraits (limpa e popula)
 * - Adiciona edges de trait_usage
 * - Adiciona edges de interface_implementation
 * 
 * @internal
 */
final class ClassVisitor implements NodeVisitor
{
    /**
     * @internal
     */
    public function supports(Node $node): bool
    {
        return $node instanceof Node\Stmt\Class_
            || $node instanceof Node\Stmt\Interface_
            || $node instanceof Node\Stmt\Trait_;
    }

    /**
     * @internal
     */
    public function enter(Node $node, VisitorContext $context): void
    {
        if ($node instanceof Node\Stmt\Interface_) {
            $this->enterInterface($node, $context);
            return;
        }

        if ($node instanceof Node\Stmt\Trait_) {
            $this->enterTrait($node, $context);
            return;
        }

        /** @var Node\Stmt\Class_ $node */
        $className = $node->name?->toString();

        if (!$className) {
            // Classe anônima: push marcador vazio para manter stack balanceada
            $context->setClass('');
            return;
        }

        // Resolver FQN (namespace + classe)
        $fqn = $context->resolveFQN($className);
        $context->setClass($fqn);

        // Extrair atributos PHP 8 da classe (#[Controller], #[AsCommand], etc.)
        $context->setClassAttributes($this->extractAttributeNames($node->attrGroups));

        // Extract HTTP base path from class-level #[Route('/prefix')] if present
        $context->setClassHttpBasePath($this->extractHttpBasePath($node->attrGroups));

        // Detectar herança (extends)
        if ($node->extends) {
            $this->processInheritance($node->extends, $fqn, $context);
        }

        // Detectar traits usados
        foreach ($node->getTraitUses() as $traitUse) {
            $this->processTraitUse($traitUse, $context);
        }

        // Detectar interfaces implementadas
        if ($node->implements) {
            foreach ($node->implements as $interface) {
                $this->processInterfaceImplementation($interface, $context);
            }
        }
    }

    /**
     * @internal
     */
    public function leave(Node $node, VisitorContext $context): void
    {
        // Limpar classe atual ao sair
        $context->setCurrentParent(null);
        $context->setClass(null);
    }

    /**
     * Extrai nomes simples de atributos PHP 8 de um grupo de atributos.
     *
     * @param \PhpParser\Node\AttributeGroup[] $attrGroups
     * @return string[]
     */
    private function extractAttributeNames(array $attrGroups): array
    {
        $names = [];
        foreach ($attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                $names[] = $attr->name->getLast();
            }
        }
        return $names;
    }

    /**
     * Extract HTTP route prefix from class-level #[Route('/prefix')] attribute.
     *
     * Returns the path string from the first positional argument of a Route attribute,
     * or null if no Route attribute with a string path is found.
     *
     * @param \PhpParser\Node\AttributeGroup[] $attrGroups
     */
    private function extractHttpBasePath(array $attrGroups): ?string
    {
        foreach ($attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                if ($attr->name->getLast() !== 'Route') {
                    continue;
                }
                foreach ($attr->args as $arg) {
                    $argName = $arg->name?->toString();
                    if (($argName === null || $argName === 'path' || $argName === 'value')
                        && $arg->value instanceof Node\Scalar\String_
                    ) {
                        return $arg->value->value;
                    }
                }
            }
        }
        return null;
    }

    /**
     * Processa herança (extends).
     *
     * Emite uma edge 'inherits' do construtor da classe filha para o anchor
     * sintético da classe pai, garantindo que a classe pai não apareça como órfã.
     *
     * @internal
     */
    private function processInheritance(Node\Name $extends, string $childFqn, VisitorContext $context): void
    {
        $parentName = $extends->toString();
        $parentFQN  = $context->resolveClass($parentName);

        $context->setCurrentParent($parentFQN);

        $context->addEdge(new \FlowEngine\Domain\Flow\Edge(
            $childFqn . '::__construct',
            $parentFQN . '::__parent',
            'extends',
            'inherits'
        ));
    }

    /**
     * Processa declaração de interface (Stmt\Interface_).
     *
     * Cria um contexto de classe para que MethodVisitor possa emitir nodes
     * marcados com isInterface:true. Nenhuma edge de trait/implements é emitida.
     *
     * @internal
     */
    private function enterInterface(Node\Stmt\Interface_ $node, VisitorContext $context): void
    {
        $interfaceName = $node->name?->toString();

        if (!$interfaceName) {
            $context->setClass('');
            return;
        }

        $fqn = $context->resolveFQN($interfaceName);
        $context->setClass($fqn);
        $context->setClassIsInterface(true);
        $context->setClassAttributes([]);
    }

    /**
     * Processa declaração de trait (Stmt\Trait_).
     *
     * Traits produzem nodes para que chamadas internas (ex: Http::) emitam edges
     * com from=Trait::method. Trait uses aninhados (trait A { use B; }) são processados.
     * Traits não têm extends nem implements.
     *
     * @internal
     */
    private function enterTrait(Node\Stmt\Trait_ $node, VisitorContext $context): void
    {
        $traitName = $node->name?->toString();

        if (!$traitName) {
            $context->setClass('');
            return;
        }

        $fqn = $context->resolveFQN($traitName);
        $context->setClass($fqn);
        $context->setClassIsTrait(true);
        $context->setClassAttributes([]);

        foreach ($node->getTraitUses() as $traitUse) {
            $this->processTraitUse($traitUse, $context);
        }
    }

    /**
     * Processa declaração de trait.
     *
     * @internal
     */
    private function processTraitUse(Node\Stmt\TraitUse $traitUse, VisitorContext $context): void
    {
        foreach ($traitUse->traits as $trait) {
            $traitName = $trait->toString();

            // Resolver FQN usando use-map (resolveClass respeita aliases de `use`)
            $traitFQN = $context->resolveClass($traitName);

            // Adicionar ao contexto
            $context->addTrait($traitFQN);

            // Criar edge de uso de trait
            // Formato: Class::__construct -> Trait::__trait
            $fromId = $context->currentClass() . '::__construct';
            $toId = $traitFQN . '::__trait';

            $context->addEdge(new \FlowEngine\Domain\Flow\Edge(
                $fromId,
                $toId,
                'use',
                'trait_usage'
            ));
        }
    }

    /**
     * Processa implementação de interface.
     * 
     * @internal
     */
    private function processInterfaceImplementation(Node\Name $interface, VisitorContext $context): void
    {
        $interfaceName = $interface->toString();

        // Resolver FQN usando use-map (resolveClass respeita aliases de `use`)
        $interfaceFQN = $context->resolveClass($interfaceName);

        // Criar edge de implementação
        // Formato: Class::__construct -> Interface::__interface
        $fromId = $context->currentClass() . '::__construct';
        $toId = $interfaceFQN . '::__interface';

        $context->addEdge(new \FlowEngine\Domain\Flow\Edge(
            $fromId,
            $toId,
            'implements',
            'interface_implementation'
        ));
    }
}
