<?php

namespace FlowEngine\Domain\Analysis;

use FlowEngine\Domain\Contracts\Flow;

/**
 * Analisa métricas de complexidade do grafo de dependências.
 * 
 * Calcula:
 * - Fan-in (quantos chamam cada método)
 * - Fan-out (quantos cada método chama)
 * - Blast radius (impacto transitivo de mudanças)
 * - Hotspots (código arriscado)
 */
final class MetricsAnalyzer
{
    /** @var array<string, int> Cache de fan-in por node ID */
    private array $fanInCache = [];

    /** @var array<string, int> Cache de fan-out por node ID */
    private array $fanOutCache = [];

    public function __construct(
        private Flow $flow
    ) {
        $this->buildCaches();
    }

    /**
     * Calcula métricas para um node específico.
     */
    public function metricsFor(string $nodeId): NodeMetrics
    {
        $fanIn = $this->fanInCache[$nodeId] ?? 0;
        $fanOut = $this->fanOutCache[$nodeId] ?? 0;
        $blastRadius = $this->calculateBlastRadius($nodeId);
        $riskLevel = NodeMetrics::calculateRiskLevel($fanIn, $fanOut);

        return new NodeMetrics(
            nodeId: $nodeId,
            fanIn: $fanIn,
            fanOut: $fanOut,
            blastRadius: $blastRadius,
            riskLevel: $riskLevel
        );
    }

    /**
     * Retorna todos os hotspots (HIGH ou CRITICAL).
     * 
     * @api
     * @return NodeMetrics[]
     */
    public function hotspots(): array
    {
        $hotspots = [];

        foreach ($this->flow->nodes() as $node) {
            $metrics = $this->metricsFor($node->id());

            if ($metrics->isHotspot()) {
                $hotspots[] = $metrics;
            }
        }

        // Ordenar por complexity score (descendente)
        usort($hotspots, fn($a, $b) => $b->complexityScore() <=> $a->complexityScore());

        return $hotspots;
    }

    /**
     * Retorna top N métodos mais acoplados.
     * 
     * @api
     * @return NodeMetrics[]
     */
    public function topCoupled(int $limit = 10): array
    {
        $metrics = [];

        foreach ($this->flow->nodes() as $node) {
            $metrics[] = $this->metricsFor($node->id());
        }

        usort($metrics, fn($a, $b) => $b->couplingScore() <=> $a->couplingScore());

        return array_slice($metrics, 0, $limit);
    }

    /**
     * Estatísticas gerais do grafo.
     * 
     * @api
     */
    public function stats(): array
    {
        $allMetrics = array_map(
            fn($node) => $this->metricsFor($node->id()),
            $this->flow->nodes()
        );

        $fanIns = array_map(fn($m) => $m->fanIn, $allMetrics);
        $fanOuts = array_map(fn($m) => $m->fanOut, $allMetrics);

        return [
            'totalNodes' => $this->flow->nodeCount(),
            'totalEdges' => $this->flow->edgeCount(),
            'avgFanIn' => count($fanIns) > 0 ? round(array_sum($fanIns) / count($fanIns), 2) : 0,
            'avgFanOut' => count($fanOuts) > 0 ? round(array_sum($fanOuts) / count($fanOuts), 2) : 0,
            'maxFanIn' => count($fanIns) > 0 ? max($fanIns) : 0,
            'maxFanOut' => count($fanOuts) > 0 ? max($fanOuts) : 0,
            'hotspotCount' => count($this->hotspots()),
        ];
    }

    /**
     * Constrói caches de fan-in e fan-out.
     * @internal 
     */
    private function buildCaches(): void
    {
        // Inicializar todos os nodes com 0
        foreach ($this->flow->nodes() as $node) {
            $this->fanInCache[$node->id()] = 0;
            $this->fanOutCache[$node->id()] = 0;
        }

        // Contar baseado nas edges
        foreach ($this->flow->edges() as $edge) {
            $from = $edge->from();
            $to = $edge->to();

            // Fan-out: quantos o FROM chama
            if (!isset($this->fanOutCache[$from])) {
                $this->fanOutCache[$from] = 0;
            }
            $this->fanOutCache[$from]++;

            // Fan-in: quantos chamam o TO
            if (!isset($this->fanInCache[$to])) {
                $this->fanInCache[$to] = 0;
            }
            $this->fanInCache[$to]++;
        }
    }

    /**
     * Calcula blast radius (impacto transitivo de mudanças).
     * 
     * Blast radius = quantos nodes seriam afetados se este node mudasse.
     * Usa BFS para calcular todos os nodes alcançáveis via edges.
     */
    private function calculateBlastRadius(string $nodeId): int
    {
        $visited = [];
        $queue = [$nodeId];
        $affected = 0;

        while (!empty($queue)) {
            $current = array_shift($queue);

            if (isset($visited[$current])) {
                continue;
            }

            $visited[$current] = true;

            // Não contar o próprio node
            if ($current !== $nodeId) {
                $affected++;
            }

            // Adicionar todos que CHAMAM este node (upstream)
            foreach ($this->flow->edges() as $edge) {
                if ($edge->to() === $current && !isset($visited[$edge->from()])) {
                    $queue[] = $edge->from();
                }
            }
        }

        return $affected;
    }
}
