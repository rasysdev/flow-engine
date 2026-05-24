<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\Application\UseCase\DTO\ProjectMapDTO;
use FlowEngine\Domain\Analysis\CycleDetector;
use FlowEngine\Domain\Analysis\MetricsAnalyzer;
use FlowEngine\Domain\Contracts\FlowRepository;

final class MapProjectStructure
{
    public function __construct(
        private FlowRepository $repository,
        private string $projectRoot = ''
    ) {
    }

    public function execute(): ProjectMapDTO
    {
        $flow = $this->repository->getFlow();
        $nodes = $flow->nodes();

        // Language detection: majority vote
        $langCounts = [];
        foreach ($nodes as $node) {
            $lang = $node->language();
            $langCounts[$lang] = ($langCounts[$lang] ?? 0) + 1;
        }
        $language = 'php';
        if (!empty($langCounts)) {
            arsort($langCounts);
            $language = (string) array_key_first($langCounts);
        }

        // Framework detection via composer.json
        $framework = $this->detectFramework();

        // Stats
        $analyzer = new MetricsAnalyzer($flow);
        $cycleDetector = new CycleDetector($flow);
        $cycles = $cycleDetector->detectCycles();
        $stats = [
            'nodes'  => $flow->nodeCount(),
            'edges'  => $flow->edgeCount(),
            'cycles' => count($cycles),
        ];

        // Top namespaces (top 5 by unique class count, prefix of 2 segments)
        $nsCounts = [];
        foreach ($nodes as $node) {
            $class = $node->class();
            $parts = explode('\\', $class);
            $ns = count($parts) >= 2
                ? $parts[0] . '\\' . $parts[1]
                : $parts[0];
            $nsCounts[$ns][$class] = true;
        }
        $nsSizes = [];
        foreach ($nsCounts as $ns => $classes) {
            $nsSizes[$ns] = count($classes);
        }
        arsort($nsSizes);
        $topNamespaces = [];
        foreach (array_slice($nsSizes, 0, 5, true) as $ns => $count) {
            $topNamespaces[] = ['namespace' => $ns, 'classes' => $count];
        }

        // Entrypoints: nodes with fan_in === 0, limit 15
        $fanInMap = [];
        foreach ($flow->edges() as $edge) {
            $target = $edge->to();
            $fanInMap[$target] = ($fanInMap[$target] ?? 0) + 1;
        }
        $entrypoints = [];
        foreach ($nodes as $node) {
            $fanIn = $fanInMap[$node->id()] ?? 0;
            if ($fanIn === 0) {
                $entrypoints[] = $node->id();
            }
        }
        $entrypoints = array_slice($entrypoints, 0, 15);

        // Hotspots top 5 by fan_in
        $hotspotMetrics = $analyzer->hotspots();
        $hotspots = [];
        foreach (array_slice($hotspotMetrics, 0, 5) as $m) {
            $hotspots[] = ['id' => $m->nodeId, 'fan_in' => $m->fanIn];
        }

        return new ProjectMapDTO(
            project:        $this->projectRoot,
            language:       $language,
            framework:      $framework,
            stats:          $stats,
            top_namespaces: $topNamespaces,
            entrypoints:    $entrypoints,
            hotspots_top5:  $hotspots
        );
    }

    private function detectFramework(): ?string
    {
        if ($this->projectRoot === '') {
            return null;
        }

        $composerFile = $this->projectRoot . '/composer.json';
        if (!is_file($composerFile)) {
            return null;
        }

        $content = @file_get_contents($composerFile);
        if ($content === false) {
            return null;
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return null;
        }

        $require    = (array) ($decoded['require'] ?? []);
        $requireDev = (array) ($decoded['require-dev'] ?? []);

        if (isset($require['laravel/framework'])) {
            return 'Laravel';
        }

        if (isset($require['symfony/framework-bundle'])) {
            return 'Symfony';
        }

        if (isset($require['nikic/php-parser']) || isset($requireDev['nikic/php-parser'])) {
            return 'Library/Generic';
        }

        return null;
    }
}
