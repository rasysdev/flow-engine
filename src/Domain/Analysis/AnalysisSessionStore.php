<?php

namespace FlowEngine\Domain\Analysis;

/**
 * Contrato para persistência de sessões de análise.
 *
 * @api
 */
interface AnalysisSessionStore
{
    /**
     * Salva uma sessão de análise.
     *
     * @param AnalysisSession $session
     */
    public function save(AnalysisSession $session): void;

    /**
     * Retorna todas as sessões.
     *
     * @return AnalysisSession[]
     */
    public function all(): array;

    /**
     * Retorna sessões filtradas por modo.
     *
     * @param string $mode 'manual', 'assisted' ou 'ai'
     * @return AnalysisSession[]
     */
    public function forMode(string $mode): array;
}
