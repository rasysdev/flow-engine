<?php

namespace FlowEngine\Infrastructure\Analyzer\Visitors;

use PhpParser\Node;

/**
 * Interface base para todos os visitors do AstParser.
 * 
 * Cada visitor é responsável por processar um tipo específico de nó AST
 * e extrair informações relevantes para o grafo de execução.
 * 
 * PRINCÍPIOS:
 * - Single Responsibility: cada visitor processa apenas um tipo de nó
 * - Stateless: visitors não mantêm estado entre arquivos
 * - Context-based: todo estado é armazenado no VisitorContext
 * 
 * @internal
 */
interface NodeVisitor
{
    /**
     * Verifica se este visitor processa o tipo de nó dado.
     * 
     * @internal
     * @param Node $node Nó AST do nikic/php-parser
     * @return bool TRUE se este visitor processa este tipo de nó
     */
    public function supports(Node $node): bool;

    /**
     * Processa o nó e atualiza o contexto.
     * 
     * @internal
     * @param Node $node Nó AST a processar
     * @param VisitorContext $context Estado compartilhado (modificado in-place)
     * @return void
     */
    public function enter(Node $node, VisitorContext $context): void;

    /**
     * Chamado quando sai do nó (opcional).
     * 
     * Útil para limpar estado temporário (ex: sair de método).
     * 
     * @internal
     * @param Node $node Nó AST do qual está saindo
     * @param VisitorContext $context Estado compartilhado
     * @return void
     */
    public function leave(Node $node, VisitorContext $context): void;
}