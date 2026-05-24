<?php

namespace FlowEngine\Domain\Output;

use FlowEngine\Domain\Analysis\MetricsAnalyzer;
use FlowEngine\Domain\Contracts\Flow;

/**
 * Gera visualização HTML do grafo de dependências.
 * 
 * Usa Mermaid.js para renderizar grafo interativo com:
 * - Nodes coloridos por layer
 * - Edges mostrando chamadas
 * - Tooltips com métricas
 * - Filtros por namespace/risk
 */
final class DependencyGraphGenerator
{
    public function __construct(
        private Flow $flow,
        private MetricsAnalyzer $metricsAnalyzer
    ) {
    }

    /**
     * Gera HTML completo com grafo interativo.
     * 
     * @api
     */
    public function generate(): string
    {
        $mermaidCode = $this->generateMermaidCode();
        $stats = $this->generateStats();

        return $this->wrapInHtml($mermaidCode, $stats);
    }

    /**
     * Gera código Mermaid do grafo.
     */
    private function generateMermaidCode(): string
    {
        $lines = ['graph LR'];

        // Nodes
        foreach ($this->flow->nodes() as $node) {
            $metrics = $this->metricsAnalyzer->metricsFor($node->id());
            $color = $this->getNodeColor($node->id(), $metrics->riskLevel);
            $label = $this->getNodeLabel($node->id());

            $lines[] = "    {$this->sanitizeId($node->id())}[\"{$label}\"]:::{$color}";
        }

        // Edges (limitar pra não ficar muito poluído)
        $edgeCount = 0;
        $maxEdges = 100; // Limitar visualização

        foreach ($this->flow->edges() as $edge) {
            if ($edgeCount++ >= $maxEdges) {
                break;
            }

            $from = $this->sanitizeId($edge->from());
            $to = $this->sanitizeId($edge->to());

            $lines[] = "    {$from} --> {$to}";
        }

        // Classes CSS
        $lines[] = "    classDef domain fill:#4CAF50,stroke:#2E7D32,color:#fff";
        $lines[] = "    classDef application fill:#2196F3,stroke:#1565C0,color:#fff";
        $lines[] = "    classDef infrastructure fill:#FF9800,stroke:#E65100,color:#fff";
        $lines[] = "    classDef hotspot fill:#F44336,stroke:#B71C1C,color:#fff";
        $lines[] = "    classDef default fill:#9E9E9E,stroke:#616161,color:#fff";

        return implode("\n", $lines);
    }

    /**
     * Gera estatísticas do grafo.
     */
    private function generateStats(): array
    {
        $stats = $this->metricsAnalyzer->stats();

        return [
            'Total Nodes' => $this->flow->nodeCount(),
            'Total Edges' => $this->flow->edgeCount(),
            'Hotspots' => $stats['hotspotCount'],
            'Avg Fan-in' => $stats['avgFanIn'],
            'Avg Fan-out' => $stats['avgFanOut'],
            'Max Complexity' => $stats['maxFanOut'],
        ];
    }

    /**
     * Determina cor do node baseado em layer e risco.
     */
    private function getNodeColor(string $nodeId, string $riskLevel): string
    {
        // Hotspots sempre vermelhos
        if (in_array($riskLevel, ['HIGH', 'CRITICAL'])) {
            return 'hotspot';
        }

        // Por layer
        if (str_contains($nodeId, 'Domain')) {
            return 'domain';
        }

        if (str_contains($nodeId, 'Application')) {
            return 'application';
        }

        if (str_contains($nodeId, 'Infrastructure')) {
            return 'infrastructure';
        }

        return 'default';
    }

    /**
     * Gera label curto pro node.
     */
    private function getNodeLabel(string $nodeId): string
    {
        $parts = explode('\\', $nodeId);
        $classAndMethod = end($parts);

        // Limitar tamanho
        if (strlen($classAndMethod) > 30) {
            return substr($classAndMethod, 0, 27) . '...';
        }

        return $classAndMethod;
    }

    /**
     * Sanitiza ID pra Mermaid (remove caracteres especiais).
     */
    private function sanitizeId(string $id): string
    {
        return 'node_' . md5($id);
    }

    /**
     * Envolve Mermaid em HTML completo.
     */
    private function wrapInHtml(string $mermaidCode, array $stats): string
    {
        $statsHtml = '';
        foreach ($stats as $key => $value) {
            $statsHtml .= "<div class='stat'><strong>{$key}:</strong> {$value}</div>";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flow Engine - Dependency Graph</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
        }
        
        header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            padding: 20px 30px;
            background: #fafafa;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .stat {
            padding: 15px;
            background: white;
            border-radius: 6px;
            border-left: 4px solid #667eea;
        }
        
        .stat strong {
            display: block;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        
        .legend {
            padding: 20px 30px;
            background: #fafafa;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .legend h3 {
            font-size: 14px;
            margin-bottom: 10px;
            color: #666;
        }
        
        .legend-items {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }
        
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            border: 2px solid rgba(0,0,0,0.2);
        }
        
        .graph-container {
            padding: 30px;
            overflow-x: auto;
        }
        
        pre.mermaid {
            margin: 0;
            overflow: auto;
            white-space: pre;
        }
        
        footer {
            padding: 20px 30px;
            background: #fafafa;
            border-top: 1px solid #e0e0e0;
            text-align: center;
            color: #666;
            font-size: 13px;
        }
        
        .warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            margin: 20px 30px;
            border-radius: 6px;
            font-size: 13px;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🔍 Flow Engine - Dependency Graph</h1>
            <p>Visual representation of code dependencies and complexity</p>
        </header>
        
        <div class="stats">
            {$statsHtml}
        </div>
        
        <div class="legend">
            <h3>Legend</h3>
            <div class="legend-items">
                <div class="legend-item">
                    <div class="legend-color" style="background: #4CAF50;"></div>
                    <span>Domain Layer</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #2196F3;"></div>
                    <span>Application Layer</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #FF9800;"></div>
                    <span>Infrastructure Layer</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #F44336;"></div>
                    <span>Hotspot (High Risk)</span>
                </div>
            </div>
        </div>
        
        <div class="warning">
            <strong>Note:</strong> For large codebases, only the first 100 edges are shown to keep the visualization readable.
            The Mermaid source below is embedded directly so this export works offline; paste it into any Mermaid renderer if you want an interactive SVG.
        </div>
        
        <div class="graph-container">
            <pre class="mermaid">{$mermaidCode}</pre>
        </div>
        
        <footer>
            Generated by Flow Engine on {$this->getTimestamp()}<br>
            <a href="https://github.com/rborges/flow-engine" target="_blank" style="color: #667eea;">Flow Engine on GitHub</a>
        </footer>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Timestamp formatado.
     */
    private function getTimestamp(): string
    {
        return date('Y-m-d H:i:s');
    }
}
