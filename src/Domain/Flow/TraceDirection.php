<?php

namespace FlowEngine\Domain\Flow;

/**
 * Direção de traversal no grafo de dependências.
 *
 * @api
 */
enum TraceDirection: string
{
    case DOWNSTREAM = 'downstream'; // quem eu chamo
    case UPSTREAM = 'upstream';     // quem me chama
    case BOTH = 'both';

    /**
     * Cria a partir de string (case-insensitive).
     *
     * @param string $value 'downstream', 'upstream' ou 'both'
     * @return self
     * @throws \ValueError Se valor inválido
     */
    public static function fromString(string $value): self
    {
        return self::from(strtolower($value));
    }
}
