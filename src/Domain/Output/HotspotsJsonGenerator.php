<?php

namespace FlowEngine\Domain\Output;

use FlowEngine\Domain\Analysis\MetricsAnalyzer;
use FlowEngine\Domain\Analysis\OrphanCodeDetector;
use FlowEngine\Domain\Contracts\Flow;

/**
 * Gera relatório JSON estruturado de hotspots e métricas.
 * 
 * Formato machine-readable pronto pra:
 * - CI/CD integration
 * - Local observability tools
 * - APIs
 */
final class HotspotsJsonGenerator
{
    public function __construct(
        private Flow $flow,
        private MetricsAnalyzer $metricsAnalyzer,
        private OrphanCodeDetector $orphanDetector
    ) {
    }

    /**
     * Gera JSON completo.
     * 
     * @api
     */
    public function generate(): string
    {
        $data = [
            'metadata' => $this->generateMetadata(),
            'summary' => $this->generateSummary(),
            'hotspots' => $this->generateHotspots(),
            'orphans' => $this->generateOrphansSummary(),
            'metrics' => $this->generateMetrics(),
        ];

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Metadados do relatório.
     */
    private function generateMetadata(): array
    {
        return [
            'generatedAt' => date('c'), // ISO 8601
            'generator' => 'Flow Engine',
            'version' => '1.0.0',
        ];
    }

    /**
     * Resumo geral.
     */
    private function generateSummary(): array
    {
        $stats = $this->metricsAnalyzer->stats();

        return [
            'totalNodes' => $stats['totalNodes'],
            'totalEdges' => $stats['totalEdges'],
            'hotspotsCount' => $stats['hotspotCount'],
            'avgFanIn' => $stats['avgFanIn'],
            'avgFanOut' => $stats['avgFanOut'],
            'maxFanIn' => $stats['maxFanIn'],
            'maxFanOut' => $stats['maxFanOut'],
        ];
    }

    /**
     * Lista de hotspots.
     */
    private function generateHotspots(): array
    {
        $hotspots = $this->metricsAnalyzer->hotspots();

        return array_map(function ($metrics) {
            return [
                'nodeId' => $metrics->nodeId,
                'riskLevel' => $metrics->riskLevel,
                'complexityScore' => $metrics->complexityScore(),
                'fanIn' => $metrics->fanIn,
                'fanOut' => $metrics->fanOut,
                'blastRadius' => $metrics->blastRadius,
                'recommendations' => $this->generateRecommendations($metrics),
            ];
        }, $hotspots);
    }

    /**
     * Resumo de órfãos.
     */
    private function generateOrphansSummary(): array
    {
        $stats = $this->orphanDetector->stats();
        $orphans = $this->orphanDetector->orphanMethods();

        // Top 20 órfãos de alta confiança
        $highConfidence = array_filter(
            $orphans,
            fn($o) => in_array($o->confidenceLevel(), ['HIGH', 'VERY_HIGH'])
        );

        return [
            'totalOrphans' => $stats['totalOrphans'],
            'highConfidenceCount' => $stats['highConfidenceOrphans'],
            'percentageOfCodebase' => $stats['percentageOrphans'],
            'topOrphans' => array_map(
                fn($o) => $o->toArray(),
                array_slice($highConfidence, 0, 20)
            ),
        ];
    }

    /**
     * Métricas detalhadas.
     */
    private function generateMetrics(): array
    {
        return [
            'topCoupled' => array_map(
                fn($m) => $m->toArray(),
                $this->metricsAnalyzer->topCoupled(10)
            ),
        ];
    }

    /**
     * Gera recomendações baseado nas métricas.
     */
    private function generateRecommendations($metrics): array
    {
        $recommendations = [];

        if ($metrics->fanOut > 8) {
            $recommendations[] = "High fan-out ({$metrics->fanOut}) indicates tight coupling. Consider breaking down into smaller methods.";
        }

        if ($metrics->fanIn > 10) {
            $recommendations[] = "High fan-in ({$metrics->fanIn}) suggests this is a critical method. Changes here have wide impact.";
        }

        if ($metrics->blastRadius > 20) {
            $recommendations[] = "High blast radius ({$metrics->blastRadius}). Changing this affects many other methods.";
        }

        if ($metrics->complexityScore() > 50) {
            $recommendations[] = "Very high complexity score. This is a prime candidate for refactoring.";
        }

        if (empty($recommendations)) {
            $recommendations[] = "Monitor this method as complexity increases.";
        }

        return $recommendations;
    }
}
