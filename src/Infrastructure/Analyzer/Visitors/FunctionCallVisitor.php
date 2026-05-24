<?php

namespace FlowEngine\Infrastructure\Analyzer\Visitors;

use PhpParser\Node;

/**
 * Detecta chamadas a funções globais ou do namespace.
 * 
 * NOVO em v3.1
 * 
 * Exemplos:
 * - var_dump($var)
 * - array_map(...)
 * - custom_helper($data)
 * - Namespace\function_name()
 * 
 * Formato da edge:
 * - From: CurrentClass::currentMethod
 * - To: ::function_name (global) ou Namespace::function_name
 * - Type: function_call
 * 
 * Limitações:
 * - Não detecta funções dinâmicas: $func()
 * - Não rastreia funções builtin (array_map, etc) por padrão
 * 
 * @internal
 */
final class FunctionCallVisitor implements NodeVisitor
{
    /** @var string[] Funções builtin para ignorar */
    private const BUILTIN_FUNCTIONS = [
        'var_dump', 'print_r', 'echo', 'die', 'exit',
        'isset', 'empty', 'unset', 'count', 'sizeof',
        'is_array', 'is_string', 'is_int', 'is_bool',
        'array_map', 'array_filter', 'array_reduce',
        'array_keys', 'array_values', 'array_merge',
        'explode', 'implode', 'str_replace', 'preg_match',
        'file_get_contents', 'file_put_contents',
        'json_encode', 'json_decode',
    ];

    /**
     * @internal
     */
    public function supports(Node $node): bool
    {
        return $node instanceof Node\Expr\FuncCall;
    }

    /**
     * @internal
     */
    public function enter(Node $node, VisitorContext $context): void
    {
        /** @var Node\Expr\FuncCall $node */
        
        if (!$context->isInsideMethod()) {
            return;
        }

        // Ignorar funções dinâmicas ($func())
        if (!$node->name instanceof Node\Name) {
            return;
        }

        $functionName = $node->name->toString();

        // Ignorar funções builtin (opcional - pode ser configurável)
        if (in_array(strtolower($functionName), self::BUILTIN_FUNCTIONS)) {
            return;
        }

        // Resolver FQN se necessário
        if (str_contains($functionName, '\\')) {
            // Já é FQN
            $fqn = $functionName;
        } elseif ($context->currentNamespace()) {
            // Adicionar namespace atual
            $fqn = $context->currentNamespace() . '\\' . $functionName;
        } else {
            // Função global
            $fqn = $functionName;
        }

        // Criar edge
        $fromId = $context->currentNodeId();
        $toId = '::' . $fqn; // :: prefix indica função (não método)

        $context->addEdge(new \FlowEngine\Domain\Flow\Edge(
            $fromId,
            $toId,
            $functionName,
            'function_call'
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
