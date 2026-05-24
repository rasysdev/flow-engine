<?php

namespace FlowEngine\Domain\Analysis;

/**
 * Value object representando uma operação dentro de uma sessão de análise.
 *
 * Tipos de operação:
 * - query: consulta via FlowQuery
 * - trace: trace de fluxo via FlowTracer
 * - execute: execução de método
 * - explain: explicação de governança
 * - ai_suggest: sugestão via AI
 *
 * @api
 */
final readonly class AnalysisOperation
{
    /**
     * @param string $type Tipo de operação (query, trace, execute, explain, ai_suggest)
     * @param string $detail Detalhes da operação (ex: "FlowQuery::byNamespace('App\\Services')")
     * @param float $durationMs Duração em milissegundos
     * @param float $timestamp Unix timestamp (microtime)
     */
    public function __construct(
        public string $type,
        public string $detail,
        public float $durationMs,
        public float $timestamp
    ) {
    }
}
