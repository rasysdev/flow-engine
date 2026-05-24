<?php

namespace FlowEngine\AI\DTO;

use FlowEngine\AI\Context\NodeContext;

/**
 * Contexto completo para assistência guiada por IA.
 * 
 * Agrega informações necessárias para a IA sugerir inputs
 * sem depender diretamente de entidades de domínio.
 * 
 * @see GuidedAssistant::suggestInputs()
 */
final class GuidedInputContext
{
    /**
     * @param NodeContext $node Representação DTO do nó
     * @param array $inputs Definição dos inputs esperados
     * @param array $visibility Contexto de visibilidade/governança
     * @param array $impact Análise de impacto upstream/downstream
     */
    public function __construct(
        public readonly NodeContext $node,
        public readonly array $inputs,
        public readonly array $visibility,
        public readonly array $impact
    ) {
    }
}