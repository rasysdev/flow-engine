<?php

namespace FlowEngine\Domain\Analysis;

/**
 * Métricas de complexidade de um Node.
 * 
 * Value Object imutável que armazena estatísticas calculadas
 * a partir do grafo de dependências.
 */
final readonly class NodeMetrics
{
    public function __construct(
        public string $nodeId,
        public int $fanIn,
        public int $fanOut,
        public int $blastRadius,
        public string $riskLevel
    ) {
    }

    /**
     * Calcula nível de risco baseado nas métricas.
     *
     * Critérios base:
     * - CRITICAL: fan-in > 20 OU fan-out > 15
     * - HIGH: fan-in > 10 OU fan-out > 8
     * - MEDIUM: fan-in > 5 OU fan-out > 5
     * - LOW: demais casos
     *
     * Cap: blastRadius conta os dependentes transitivos do nó (todos que o
     * alcançam pela cadeia de chamadas). Quando fanIn === 0 e blastRadius === 0
     * não há chamador direto nem dependente transitivo, então alterar o nó não
     * pode quebrar ninguém e CRITICAL é rebaixado para HIGH. As duas condições
     * são checadas em conjunto de propósito: blastRadius null significa "não
     * informado pelo chamador" e nesse caso o cap NÃO é aplicado — não confundir
     * blast desconhecido com blast zero.
     */
    public static function calculateRiskLevel(int $fanIn, int $fanOut, ?int $blastRadius = null): string
    {
        $level = match (true) {
            $fanIn > 20 || $fanOut > 15 => 'CRITICAL',
            $fanIn > 10 || $fanOut > 8 => 'HIGH',
            $fanIn > 5 || $fanOut > 5 => 'MEDIUM',
            default => 'LOW',
        };

        // No direct callers and nothing depending on it transitively: changing
        // this node cannot break anyone, so a high fan-out alone must not flag it
        // CRITICAL. blastRadius === null means "unknown", so the cap stays off.
        if ($level === 'CRITICAL' && $fanIn === 0 && $blastRadius === 0) {
            return 'HIGH';
        }

        return $level;
    }

    /**
     * Verifica se é um hotspot (método problemático).
     */
    public function isHotspot(): bool
    {
        return in_array($this->riskLevel, ['CRITICAL', 'HIGH'], true);
    }

    /**
     * Score numérico de complexidade (0-100).
     *
     * Fórmula: (fan-in * 2) + (fan-out * 3) + (blast-radius * 0.5)
     * Normalizado para máximo 100.
     */
    public function complexityScore(): int
    {
        $score = ($this->fanIn * 2) + ($this->fanOut * 3) + ($this->blastRadius * 0.5);
        return min(100, (int) $score);
    }

    /**
     * Score de acoplamento: total de conexões diretas (fan-in + fan-out).
     */
    public function couplingScore(): int
    {
        return $this->fanIn + $this->fanOut;
    }

    /**
     * Serializa para array.
     */
    public function toArray(): array
    {
        return [
            'nodeId' => $this->nodeId,
            'fanIn' => $this->fanIn,
            'fanOut' => $this->fanOut,
            'blastRadius' => $this->blastRadius,
            'riskLevel' => $this->riskLevel,
            'complexityScore' => $this->complexityScore(),
            'coupling' => $this->couplingScore(),
            'isHotspot' => $this->isHotspot(),
        ];
    }
}
