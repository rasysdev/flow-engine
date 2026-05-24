<?php

namespace FlowEngine\Application\AppMap;

final class ComplianceMonitorEvaluator
{
    /**
     * @param array<string, mixed> $diff Output from AppMapDiffAnalyzer::diff
     * @param array<string, mixed> $policy
     * @return array<string, mixed>
     */
    public function evaluate(array $diff, array $policy): array
    {
        $violations = [];

        $summary = is_array($diff['summary'] ?? null) ? $diff['summary'] : [];
        $thresholds = is_array($policy['thresholds'] ?? null) ? $policy['thresholds'] : [];
        $blockers = is_array($policy['blockers'] ?? null) ? $policy['blockers'] : [];

        $thresholdMetrics = [
            'servicesAdded',
            'servicesRemoved',
            'serviceEdgesAdded',
            'serviceEdgesRemoved',
            'inconsistenciesAdded',
            'breakingChanges',
        ];

        foreach ($thresholdMetrics as $metric) {
            $key = $metric . 'Max';
            if (!array_key_exists($key, $thresholds)) {
                continue;
            }

            $actual = (int) ($summary[$metric] ?? 0);
            $max = (int) $thresholds[$key];
            if ($actual <= $max) {
                continue;
            }

            $violations[] = [
                'code' => 'THRESHOLD_EXCEEDED_' . strtoupper($metric),
                'severity' => 'warn',
                'source' => 'threshold',
                'metric' => $metric,
                'actual' => $actual,
                'max' => $max,
                'message' => "Threshold exceeded: {$metric}={$actual} > {$key}={$max}",
            ];
        }

        $blockedEdgeTypes = is_array($blockers['edgeTypes'] ?? null) ? $blockers['edgeTypes'] : [];
        if ($blockedEdgeTypes !== []) {
            $addedEdges = is_array($diff['serviceEdges']['addedDetails'] ?? null)
                ? $diff['serviceEdges']['addedDetails']
                : [];
            $blockedCounts = [];

            foreach ($addedEdges as $edge) {
                if (!is_array($edge)) {
                    continue;
                }
                $type = (string) ($edge['type'] ?? '');
                if ($type === '' || !in_array($type, $blockedEdgeTypes, true)) {
                    continue;
                }
                $blockedCounts[$type] = (int) ($blockedCounts[$type] ?? 0) + 1;
            }

            foreach ($blockedCounts as $type => $count) {
                $violations[] = [
                    'code' => 'BLOCKED_EDGE_TYPE_ADDED',
                    'severity' => 'fail',
                    'source' => 'blocker',
                    'message' => "Blocked edge type added: {$type}",
                    'context' => [
                        'edgeType' => $type,
                        'count' => $count,
                    ],
                ];
            }
        }

        $blockedSeverities = is_array($blockers['inconsistencySeverities'] ?? null)
            ? $blockers['inconsistencySeverities']
            : [];
        if ($blockedSeverities !== []) {
            $addedInconsistencies = is_array($diff['inconsistencies']['addedDetails'] ?? null)
                ? $diff['inconsistencies']['addedDetails']
                : [];
            $blockedCounts = [];

            foreach ($addedInconsistencies as $issue) {
                if (!is_array($issue)) {
                    continue;
                }
                $severity = (string) ($issue['severity'] ?? '');
                if ($severity === '' || !in_array($severity, $blockedSeverities, true)) {
                    continue;
                }
                $blockedCounts[$severity] = (int) ($blockedCounts[$severity] ?? 0) + 1;
            }

            foreach ($blockedCounts as $severity => $count) {
                $violations[] = [
                    'code' => 'BLOCKED_INCONSISTENCY_SEVERITY_ADDED',
                    'severity' => 'fail',
                    'source' => 'blocker',
                    'message' => "Blocked inconsistency severity added: {$severity}",
                    'context' => [
                        'inconsistencySeverity' => $severity,
                        'count' => $count,
                    ],
                ];
            }
        }

        if (($blockers['breakingDependencyChanges'] ?? false) === true) {
            $breakingTotal = (int) (($diff['breakingChanges']['total'] ?? null) ?? 0);
            if ($breakingTotal > 0) {
                $violations[] = [
                    'code' => 'BLOCKED_BREAKING_DEPENDENCY_CHANGES',
                    'severity' => 'fail',
                    'source' => 'blocker',
                    'actual' => $breakingTotal,
                    'message' => "Blocked breaking dependency changes detected: {$breakingTotal}",
                ];
            }
        }

        $status = 'pass';
        foreach ($violations as $violation) {
            if (($violation['severity'] ?? '') === 'fail') {
                $status = 'fail';
                break;
            }
            $status = 'warn';
        }

        $ordered = $this->orderViolationsBySeverity($violations);
        $topReasons = array_slice(array_values(array_unique(array_map(
            static fn(array $v): string => (string) ($v['message'] ?? ''),
            $ordered
        ))), 0, 5);

        $failCount = count(array_filter(
            $ordered,
            static fn(array $v): bool => ($v['severity'] ?? '') === 'fail'
        ));
        $warnCount = count(array_filter(
            $ordered,
            static fn(array $v): bool => ($v['severity'] ?? '') === 'warn'
        ));

        return [
            'status' => $status,
            'approvalRequired' => $status !== 'pass',
            'generatedAt' => date('c'),
            'riskSummary' => [
                'totalFindings' => count($ordered),
                'failCount' => $failCount,
                'warnCount' => $warnCount,
                'breakingChanges' => (int) ($summary['breakingChanges'] ?? 0),
                'servicesRemoved' => (int) ($summary['servicesRemoved'] ?? 0),
                'serviceEdgesRemoved' => (int) ($summary['serviceEdgesRemoved'] ?? 0),
                'inconsistenciesAdded' => (int) ($summary['inconsistenciesAdded'] ?? 0),
            ],
            'topReasons' => $topReasons,
            'violations' => $ordered,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $violations
     * @return array<int, array<string, mixed>>
     */
    private function orderViolationsBySeverity(array $violations): array
    {
        usort($violations, static function (array $a, array $b): int {
            $rank = ['fail' => 0, 'warn' => 1];
            $aRank = $rank[(string) ($a['severity'] ?? 'warn')] ?? 1;
            $bRank = $rank[(string) ($b['severity'] ?? 'warn')] ?? 1;

            if ($aRank !== $bRank) {
                return $aRank <=> $bRank;
            }

            return strcmp((string) ($a['code'] ?? ''), (string) ($b['code'] ?? ''));
        });

        return $violations;
    }
}
