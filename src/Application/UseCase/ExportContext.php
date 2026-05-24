<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\AI\Export\ContextExporter;
use FlowEngine\AI\Export\ExportOptions;
use FlowEngine\Application\DTO\ArchitectureReportDTO;
use FlowEngine\Application\DTO\ComplexityReportDTO;
use FlowEngine\Application\DTO\ContextExportDTO;
use FlowEngine\Application\DTO\CycleReportDTO;
use FlowEngine\Application\DTO\MetricsReportDTO;
use FlowEngine\Application\DTO\OrphanReportDTO;
use FlowEngine\Domain\Contracts\Flow;
use FlowEngine\Domain\Flow\FlowTracer;

final class ExportContext
{
    /**
     * @param array<string, mixed>|null $appmapData Pre-built appmap data from ApplicationMapBuilder::build()
     */
    public function __construct(
        private AnalyzeMetrics $analyzeMetrics,
        private AnalyzeComplexity $analyzeComplexity,
        private AnalyzeCycles $analyzeCycles,
        private AnalyzeArchitecture $analyzeArchitecture,
        private AnalyzeOrphans $analyzeOrphans,
        private ContextExporter $contextExporter,
        private ?array $appmapData = null,
        private ?string $projectRoot = null,
        private ?Flow $flow = null
    ) {
    }

    public function execute(?ExportOptions $options = null): ContextExportDTO
    {
        $options ??= ExportOptions::all();
        $reports = [];
        $sections = [];

        // Extract subgraph IDs when --entrypoint is set
        $subgraphIds = null;
        if ($options->entrypoint !== null && $this->flow !== null) {
            try {
                $tracer = new FlowTracer($this->flow);
                $subgraphIds = $tracer->extractSubgraph(
                    $options->entrypoint,
                    $options->entrypointDepth
                );
            } catch (\InvalidArgumentException) {
                if ($options->strictEntrypoint) {
                    return new ContextExportDTO(markdown: '', tokenEstimate: 0, includedSections: []);
                }
                // node not found — export without scope, user sees note in header
            }
        }

        if ($options->includeServiceMap && $this->appmapData !== null) {
            $reports['serviceMap'] = $this->appmapData;
            $sections[] = 'serviceMap';
        }

        if ($options->includeMetrics) {
            $reports['metrics'] = $this->analyzeMetrics->execute();
            if ($subgraphIds !== null) {
                $r = $reports['metrics'];
                $hs = array_values(array_filter($r->hotspots, fn($h) => isset($subgraphIds[$h['nodeId'] ?? ''])));
                $tc = array_values(array_filter($r->topCoupled, fn($c) => isset($subgraphIds[$c['nodeId'] ?? ''])));
                $reports['metrics'] = new MetricsReportDTO(
                    totalNodes: $r->totalNodes, totalEdges: $r->totalEdges,
                    avgFanIn: $r->avgFanIn, avgFanOut: $r->avgFanOut,
                    maxFanIn: $r->maxFanIn, maxFanOut: $r->maxFanOut,
                    hotspotCount: count($hs), hotspots: $hs, topCoupled: $tc
                );
            }
            $sections[] = 'metrics';

            // Build signatures map from flow nodes
            if ($this->flow !== null) {
                $reports['signatures'] = $this->buildSignatures($this->flow, $subgraphIds);
            }
        }

        if ($options->includeComplexity) {
            $reports['complexity'] = $this->analyzeComplexity->execute();
            if ($subgraphIds !== null) {
                $r = $reports['complexity'];
                $cm = array_values(array_filter($r->complexMethods, fn($m) => isset($subgraphIds[$m['nodeId'] ?? ''])));
                $reports['complexity'] = new ComplexityReportDTO(
                    totalMethods: $r->totalMethods, avgComplexity: $r->avgComplexity,
                    maxComplexity: $r->maxComplexity, minComplexity: $r->minComplexity,
                    byLevel: $r->byLevel, complexMethods: $cm
                );
            }
            $sections[] = 'complexity';
        }

        if ($options->includeCycles) {
            $reports['cycles'] = $this->analyzeCycles->execute();
            if ($subgraphIds !== null) {
                $r = $reports['cycles'];
                $keys = array_keys($subgraphIds);
                $cy = array_values(array_filter(
                    $r->cycles,
                    fn($c) => !empty(array_intersect($c['nodes'] ?? [], $keys))
                ));
                $reports['cycles'] = new CycleReportDTO(
                    totalCycles: count($cy), totalNodesInCycles: $r->totalNodesInCycles,
                    bySeverity: $r->bySeverity, largestCycle: $r->largestCycle, cycles: $cy
                );
            }
            $sections[] = 'cycles';
        }

        if ($options->includeArchitecture) {
            $reports['architecture'] = $this->analyzeArchitecture->execute();
            if ($subgraphIds !== null) {
                $r = $reports['architecture'];
                $vl = array_values(array_filter(
                    $r->violations,
                    fn($v) => isset($subgraphIds[$v['from'] ?? '']) || isset($subgraphIds[$v['to'] ?? ''])
                ));
                $reports['architecture'] = new ArchitectureReportDTO(
                    isClean: empty($vl), totalViolations: count($vl),
                    bySeverity: $r->bySeverity, byType: $r->byType,
                    layerDistribution: $r->layerDistribution, violations: $vl
                );
            }
            $sections[] = 'architecture';
        }

        if ($options->includeOrphans) {
            $reports['orphans'] = $this->analyzeOrphans->execute();
            if ($subgraphIds !== null) {
                $r = $reports['orphans'];
                $or = array_values(array_filter($r->orphans, fn($o) => isset($subgraphIds[$o['nodeId'] ?? ''])));
                $sl = array_values(array_filter($r->suspiciousLeaves, fn($l) => isset($subgraphIds[$l['nodeId'] ?? ''])));
                $hc = count(array_filter($or, fn($o) => ($o['confidence'] ?? 0) >= 0.6));
                $reports['orphans'] = new OrphanReportDTO(
                    totalOrphans: count($or), highConfidenceOrphans: $hc,
                    suspiciousLeafNodes: count($sl), percentageOrphans: $r->percentageOrphans,
                    orphans: $or, suspiciousLeaves: $sl
                );
            }
            $sections[] = 'orphans';
        }

        if ($options->includeDataModel && $this->flow !== null) {
            $models = $this->extractModels($this->flow, $subgraphIds);
            if ($models !== []) {
                $reports['dataModel'] = $models;
                $sections[] = 'dataModel';
            }
        }

        if ($options->includeRoutes && $this->projectRoot !== null) {
            $routes = $this->extractRoutes($this->projectRoot);
            if ($routes !== []) {
                $reports['routes'] = $routes;
                $sections[] = 'routes';
            }
        }

        $markdown = $this->contextExporter->export($reports, $options);
        $tokenEstimate = (int) ceil(strlen($markdown) / 4);

        return new ContextExportDTO(
            markdown: $markdown,
            tokenEstimate: $tokenEstimate,
            includedSections: $sections
        );
    }

    /**
     * @param array<string, true>|null $subgraphIds
     * @return array<string, string> nodeId => formatted signature
     */
    private function buildSignatures(Flow $flow, ?array $subgraphIds = null): array
    {
        $signatures = [];

        foreach ($flow->nodes() as $node) {
            if ($subgraphIds !== null && !isset($subgraphIds[$node->id()])) {
                continue;
            }
            $meta = $node->metadata();
            if ($meta === null) {
                continue;
            }

            $params = $meta['params'] ?? [];
            $returnType = $meta['returnType'] ?? null;

            if ($params === [] && $returnType === null) {
                continue;
            }

            $parts = [];
            foreach ($params as $p) {
                $part = '';
                if (isset($p['type'])) {
                    $part .= $p['type'] . ' ';
                }
                $part .= $p['name'];
                $parts[] = $part;
            }

            $class = $node->class();
            $method = $node->method();
            $shortClass = str_contains($class, '\\')
                ? substr($class, (int) strrpos($class, '\\') + 1)
                : $class;

            $sig = $shortClass . '::' . $method . '(' . implode(', ', $parts) . ')';
            if ($returnType !== null) {
                $sig .= ': ' . $returnType;
            }

            $signatures[$node->id()] = $sig;
        }

        return $signatures;
    }

    /**
     * @param array<string, true>|null $subgraphIds
     * @return array<int, array<string, mixed>>
     */
    private function extractModels(Flow $flow, ?array $subgraphIds = null): array
    {
        $models = [];
        foreach ($flow->nodes() as $node) {
            if ($subgraphIds !== null && !isset($subgraphIds[$node->id()])) {
                continue;
            }
            if ($node->method() === '__model' && $node->metadata() !== null) {
                $models[] = array_merge(['class' => $node->class()], $node->metadata());
            }
        }

        return $models;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractRoutes(string $projectRoot): array
    {
        $routeFiles = glob($projectRoot . '/routes/*.php');
        if ($routeFiles === false || $routeFiles === []) {
            return [];
        }

        $routes = [];
        foreach ($routeFiles as $file) {
            $content = @file_get_contents($file);
            if ($content === false) {
                continue;
            }

            $useImports = $this->resolveRouteUseImports($content);
            $routes = array_merge($routes, $this->extractRoutesFromContent($content, $useImports));
        }

        return $routes;
    }

    /**
     * @return array<string, string> shortName => FQN
     */
    private function resolveRouteUseImports(string $content): array
    {
        $imports = [];
        if (preg_match_all('/^use\s+([A-Za-z0-9_\\\\]+)(?:\s+as\s+([A-Za-z0-9_]+))?;/m', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $fqn = $m[1];
                $alias = $m[2] ?? null;

                if ($alias !== null) {
                    $imports[$alias] = $fqn;
                } else {
                    $short = substr($fqn, (int) strrpos($fqn, '\\') + 1);
                    $imports[$short] = $fqn;
                }
            }
        }

        return $imports;
    }

    /**
     * @param array<string, string> $useImports
     * @return array<int, array{method: string, uri: string, controller: string, action: string}>
     */
    private function extractRoutesFromContent(string $content, array $useImports): array
    {
        $routes = [];

        $arrayPattern = '/Route\s*::\s*(get|post|put|patch|delete|options|any|match)\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*\[\s*([A-Za-z0-9_\\\\]+)\s*::\s*class\s*,\s*[\'"]([^\'"]+)[\'"]\s*\]/i';
        if (preg_match_all($arrayPattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $controller = $this->resolveRouteController($m[3], $useImports);
                $routes[] = [
                    'method' => strtoupper($m[1]),
                    'uri' => $m[2],
                    'controller' => $controller,
                    'action' => $m[4],
                ];
            }
        }

        $legacyPattern = '/Route\s*::\s*(get|post|put|patch|delete|options|any|match)\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]([A-Za-z0-9_\\\\]+)@([A-Za-z0-9_]+)[\'"]\s*\)/i';
        if (preg_match_all($legacyPattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $controller = $this->resolveRouteController($m[3], $useImports);
                $routes[] = [
                    'method' => strtoupper($m[1]),
                    'uri' => $m[2],
                    'controller' => $controller,
                    'action' => $m[4],
                ];
            }
        }

        $resourcePattern = '/Route\s*::\s*(resource|apiResource)\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*([A-Za-z0-9_\\\\]+)\s*::\s*class\s*\)/i';
        if (preg_match_all($resourcePattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $type = strtolower($m[1]);
                $uri = $m[2];
                $controller = $this->resolveRouteController($m[3], $useImports);

                if ($type === 'apiresource') {
                    $routes[] = ['method' => 'GET', 'uri' => $uri, 'controller' => $controller, 'action' => 'index'];
                    $routes[] = ['method' => 'POST', 'uri' => $uri, 'controller' => $controller, 'action' => 'store'];
                    $routes[] = ['method' => 'GET', 'uri' => $uri . '/{' . rtrim($uri, 's') . '}', 'controller' => $controller, 'action' => 'show'];
                    $routes[] = ['method' => 'PUT', 'uri' => $uri . '/{' . rtrim($uri, 's') . '}', 'controller' => $controller, 'action' => 'update'];
                    $routes[] = ['method' => 'DELETE', 'uri' => $uri . '/{' . rtrim($uri, 's') . '}', 'controller' => $controller, 'action' => 'destroy'];
                } else {
                    $routes[] = ['method' => 'GET', 'uri' => $uri, 'controller' => $controller, 'action' => 'index'];
                    $routes[] = ['method' => 'GET', 'uri' => $uri . '/create', 'controller' => $controller, 'action' => 'create'];
                    $routes[] = ['method' => 'POST', 'uri' => $uri, 'controller' => $controller, 'action' => 'store'];
                    $routes[] = ['method' => 'GET', 'uri' => $uri . '/{' . rtrim($uri, 's') . '}', 'controller' => $controller, 'action' => 'show'];
                    $routes[] = ['method' => 'GET', 'uri' => $uri . '/{' . rtrim($uri, 's') . '}/edit', 'controller' => $controller, 'action' => 'edit'];
                    $routes[] = ['method' => 'PUT', 'uri' => $uri . '/{' . rtrim($uri, 's') . '}', 'controller' => $controller, 'action' => 'update'];
                    $routes[] = ['method' => 'DELETE', 'uri' => $uri . '/{' . rtrim($uri, 's') . '}', 'controller' => $controller, 'action' => 'destroy'];
                }
            }
        }

        $closurePattern = '/Route\s*::\s*(get|post|put|patch|delete|options|any)\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*(?:function\b|fn\s*\()/i';
        if (preg_match_all($closurePattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $routes[] = [
                    'method' => strtoupper($m[1]),
                    'uri' => $m[2],
                    'controller' => 'Closure',
                    'action' => '-',
                ];
            }
        }

        return $routes;
    }

    /**
     * @param array<string, string> $useImports
     */
    private function resolveRouteController(string $name, array $useImports): string
    {
        if (str_contains($name, '\\')) {
            return ltrim($name, '\\');
        }

        return $useImports[$name] ?? $name;
    }
}
