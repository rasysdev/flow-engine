<?php

namespace FlowEngine\Domain\Analysis;

/**
 * Compares two analysis snapshots and produces structured deltas.
 *
 * Both baseline and current are arrays keyed by report type:
 * metrics, cycles, architecture, orphans, complexity.
 */
final class SnapshotComparator
{
    /**
     * @param array<string, array> $baseline
     * @param array<string, array> $current
     * @return array{metrics: array, cycles: array, violations: array, orphans: array, complexity: array, summary: array}
     */
    public function compare(array $baseline, array $current): array
    {
        $metrics = $this->compareMetrics(
            $baseline['metrics'] ?? [],
            $current['metrics'] ?? []
        );

        $cycles = $this->compareCycles(
            $baseline['cycles'] ?? [],
            $current['cycles'] ?? []
        );

        $violations = $this->compareViolations(
            $baseline['architecture'] ?? [],
            $current['architecture'] ?? []
        );

        $orphans = $this->compareOrphans(
            $baseline['orphans'] ?? [],
            $current['orphans'] ?? []
        );

        $complexity = $this->compareComplexity(
            $baseline['complexity'] ?? [],
            $current['complexity'] ?? []
        );

        $improved = 0;
        $degraded = 0;
        $unchanged = 0;

        // Metrics deltas
        foreach ($metrics as $key => $entry) {
            if (!is_array($entry) || !isset($entry[2])) {
                continue;
            }
            if ($entry[2] < 0) {
                $improved++;
            } elseif ($entry[2] > 0) {
                $degraded++;
            } else {
                $unchanged++;
            }
        }

        // Cycles/violations/orphans deltas
        if ($cycles['totalDelta'] < 0) {
            $improved++;
        } elseif ($cycles['totalDelta'] > 0) {
            $degraded++;
        } else {
            $unchanged++;
        }

        if ($violations['totalDelta'] < 0) {
            $improved++;
        } elseif ($violations['totalDelta'] > 0) {
            $degraded++;
        } else {
            $unchanged++;
        }

        if ($orphans['totalDelta'] < 0) {
            $improved++;
        } elseif ($orphans['totalDelta'] > 0) {
            $degraded++;
        } else {
            $unchanged++;
        }

        return [
            'metrics' => $metrics,
            'cycles' => $cycles,
            'violations' => $violations,
            'orphans' => $orphans,
            'complexity' => $complexity,
            'summary' => [
                'improved' => $improved,
                'degraded' => $degraded,
                'unchanged' => $unchanged,
            ],
        ];
    }

    private function compareMetrics(array $baseline, array $current): array
    {
        $keys = ['totalNodes', 'totalEdges', 'avgFanIn', 'avgFanOut', 'maxFanIn', 'maxFanOut', 'hotspotCount'];
        $result = [];

        foreach ($keys as $key) {
            $old = $baseline[$key] ?? 0;
            $new = $current[$key] ?? 0;
            $delta = is_float($old) || is_float($new)
                ? round($new - $old, 2)
                : $new - $old;
            $result[$key] = [$old, $new, $delta];
        }

        return $result;
    }

    private function compareCycles(array $baseline, array $current): array
    {
        $baselineCycles = $this->extractCycleNodeSets($baseline['cycles'] ?? []);
        $currentCycles = $this->extractCycleNodeSets($current['cycles'] ?? []);

        $new = array_values(array_diff($currentCycles, $baselineCycles));
        $resolved = array_values(array_diff($baselineCycles, $currentCycles));

        $baselineTotal = $baseline['totalCycles'] ?? 0;
        $currentTotal = $current['totalCycles'] ?? 0;

        return [
            'new' => $new,
            'resolved' => $resolved,
            'totalDelta' => $currentTotal - $baselineTotal,
        ];
    }

    private function compareViolations(array $baseline, array $current): array
    {
        $baselineViolations = $this->extractViolationKeys($baseline['violations'] ?? []);
        $currentViolations = $this->extractViolationKeys($current['violations'] ?? []);

        $new = array_values(array_diff($currentViolations, $baselineViolations));
        $resolved = array_values(array_diff($baselineViolations, $currentViolations));

        $baselineTotal = $baseline['totalViolations'] ?? 0;
        $currentTotal = $current['totalViolations'] ?? 0;

        return [
            'new' => $new,
            'resolved' => $resolved,
            'totalDelta' => $currentTotal - $baselineTotal,
        ];
    }

    private function compareOrphans(array $baseline, array $current): array
    {
        $baselineOrphans = $this->extractOrphanIds($baseline['orphans'] ?? []);
        $currentOrphans = $this->extractOrphanIds($current['orphans'] ?? []);

        $new = array_values(array_diff($currentOrphans, $baselineOrphans));
        $resolved = array_values(array_diff($baselineOrphans, $currentOrphans));

        $baselineTotal = $baseline['totalOrphans'] ?? 0;
        $currentTotal = $current['totalOrphans'] ?? 0;

        return [
            'new' => $new,
            'resolved' => $resolved,
            'totalDelta' => $currentTotal - $baselineTotal,
        ];
    }

    private function compareComplexity(array $baseline, array $current): array
    {
        $baselineMethods = $this->indexComplexityByNode($baseline['complexMethods'] ?? []);
        $currentMethods = $this->indexComplexityByNode($current['complexMethods'] ?? []);

        $allNodes = array_unique(array_merge(
            array_keys($baselineMethods),
            array_keys($currentMethods)
        ));

        $improved = [];
        $degraded = [];

        foreach ($allNodes as $nodeId) {
            $oldComplexity = $baselineMethods[$nodeId] ?? 0;
            $newComplexity = $currentMethods[$nodeId] ?? 0;

            if ($newComplexity < $oldComplexity) {
                $improved[] = [
                    'nodeId' => $nodeId,
                    'old' => $oldComplexity,
                    'new' => $newComplexity,
                ];
            } elseif ($newComplexity > $oldComplexity) {
                $degraded[] = [
                    'nodeId' => $nodeId,
                    'old' => $oldComplexity,
                    'new' => $newComplexity,
                ];
            }
        }

        $baselineAvg = $baseline['avgComplexity'] ?? 0.0;
        $currentAvg = $current['avgComplexity'] ?? 0.0;

        return [
            'improved' => $improved,
            'degraded' => $degraded,
            'avgDelta' => round($currentAvg - $baselineAvg, 2),
        ];
    }

    /**
     * @return string[] Serialized cycle node sets for comparison
     */
    private function extractCycleNodeSets(array $cycles): array
    {
        return array_map(function (array $cycle) {
            $nodes = $cycle['nodes'] ?? [];
            sort($nodes);
            return implode(',', $nodes);
        }, $cycles);
    }

    /**
     * @return string[] Violation keys (from→to)
     */
    private function extractViolationKeys(array $violations): array
    {
        return array_map(function (array $v) {
            return ($v['from'] ?? '') . '->' . ($v['to'] ?? '');
        }, $violations);
    }

    /**
     * @return string[] Orphan node IDs
     */
    private function extractOrphanIds(array $orphans): array
    {
        return array_map(fn(array $o) => $o['nodeId'] ?? '', $orphans);
    }

    /**
     * @return array<string, int> nodeId => complexity
     */
    private function indexComplexityByNode(array $complexMethods): array
    {
        $indexed = [];

        foreach ($complexMethods as $method) {
            $nodeId = $method['nodeId'] ?? '';
            $indexed[$nodeId] = $method['complexity'] ?? 0;
        }

        return $indexed;
    }
}
