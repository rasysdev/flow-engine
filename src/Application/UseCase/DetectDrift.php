<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\Domain\Contracts\SnapshotStorePort;

final class DetectDrift
{
    public function __construct(
        private SnapshotStorePort $store
    ) {
    }

    /**
     * @return array{status: string, drift: array, baseline: array|null, current: array}
     */
    public function execute(string $baselineLabel, array $currentSnapshot): array
    {
        if (!$this->store->exists($baselineLabel)) {
            return [
                'status' => 'error',
                'message' => "Baseline snapshot '{$baselineLabel}' not found",
                'drift' => [],
                'baseline' => null,
                'current' => [],
            ];
        }

        $baselineData = $this->store->load($baselineLabel);
        $drift = $this->computeDrift($baselineData, $currentSnapshot);

        return [
            'status' => 'ok',
            'drift' => $drift,
            'baseline' => [
                'label' => $baselineLabel,
            ],
            'current' => [
                'timestamp' => date('Y-m-d H:i:s'),
            ],
        ];
    }

    /**
     * @return array{services: array, dependencies: array, inconsistencies: array, violations: array}
     */
    private function computeDrift(array $baseline, array $current): array
    {
        $drift = [
            'services' => $this->detectServicesDrift($baseline, $current),
            'dependencies' => $this->detectDependenciesDrift($baseline, $current),
            'inconsistencies' => $this->detectInconsistenciesDrift($baseline, $current),
            'violations' => $this->detectViolationsDrift($baseline, $current),
        ];

        return $drift;
    }

    private function detectServicesDrift(array $baseline, array $current): array
    {
        $baselineServices = $this->extractServices($baseline);
        $currentServices = $this->extractServices($current);

        $added = array_diff($currentServices, $baselineServices);
        $removed = array_diff($baselineServices, $currentServices);

        return [
            'added' => array_values($added),
            'removed' => array_values($removed),
            'total_baseline' => count($baselineServices),
            'total_current' => count($currentServices),
            'delta' => count($currentServices) - count($baselineServices),
        ];
    }

    private function detectDependenciesDrift(array $baseline, array $current): array
    {
        $baselineDeps = $this->extractDependencies($baseline);
        $currentDeps = $this->extractDependencies($current);

        $added = array_diff($currentDeps, $baselineDeps);
        $removed = array_diff($baselineDeps, $currentDeps);

        return [
            'added' => array_values($added),
            'removed' => array_values($removed),
            'total_baseline' => count($baselineDeps),
            'total_current' => count($currentDeps),
            'delta' => count($currentDeps) - count($baselineDeps),
        ];
    }

    private function detectInconsistenciesDrift(array $baseline, array $current): array
    {
        $baselineInc = $baseline['inconsistencies'] ?? [];
        $currentInc = $current['inconsistencies'] ?? [];

        return [
            'total_baseline' => count($baselineInc),
            'total_current' => count($currentInc),
            'delta' => count($currentInc) - count($baselineInc),
            'increased' => count($currentInc) > count($baselineInc),
        ];
    }

    private function detectViolationsDrift(array $baseline, array $current): array
    {
        $baselineViolations = $baseline['violations'] ?? $baseline['architectureViolations'] ?? [];
        $currentViolations = $current['violations'] ?? $current['architectureViolations'] ?? [];

        return [
            'total_baseline' => count($baselineViolations),
            'total_current' => count($currentViolations),
            'delta' => count($currentViolations) - count($baselineViolations),
            'increased' => count($currentViolations) > count($baselineViolations),
        ];
    }

    /**
     * @return string[]
     */
    private function extractServices(array $data): array
    {
        if (isset($data['services']) && is_array($data['services'])) {
            return array_map(fn($s) => $s['name'] ?? $s['id'] ?? '', $data['services']);
        }
        return [];
    }

    /**
     * @return string[]
     */
    private function extractDependencies(array $data): array
    {
        $deps = [];

        if (isset($data['dependencies']) && is_array($data['dependencies'])) {
            foreach ($data['dependencies'] as $dep) {
                $source = $dep['source'] ?? '';
                $target = $dep['target'] ?? '';
                if ($source !== '' && $target !== '') {
                    $deps[] = "{$source}->{$target}";
                }
            }
        }

        if (isset($data['serviceEdges']) && is_array($data['serviceEdges'])) {
            foreach ($data['serviceEdges'] as $edge) {
                $from = $edge['from'] ?? '';
                $to = $edge['to'] ?? '';
                if ($from !== '' && $to !== '') {
                    $deps[] = "{$from}->{$to}";
                }
            }
        }

        return array_unique($deps);
    }
}
