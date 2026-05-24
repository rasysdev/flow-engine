<?php

namespace FlowEngine\Application\UseCase;

final class EvaluateDriftPolicy
{
    /**
     * @param array{services: array, dependencies: array, inconsistencies: array, violations: array} $drift
     * @param array{thresholds?: array, blockers?: array} $policy
     * @return array{passed: bool, severity: string, alerts: array, violations: array}
     */
    public function execute(array $drift, array $policy): array
    {
        $thresholds = $policy['thresholds'] ?? [];
        $blockers = $policy['blockers'] ?? [];

        $alerts = [];
        $policyViolations = [];
        $maxSeverity = 'LOW';

        // Check services removed
        if (isset($thresholds['max_services_removed'])) {
            $removed = count($drift['services']['removed'] ?? []);
            if ($removed > $thresholds['max_services_removed']) {
                $severity = $this->determineSeverity($removed, $thresholds['max_services_removed']);
                $maxSeverity = $this->maxSeverity($maxSeverity, $severity);
                $alerts[] = [
                    'type' => 'services_removed',
                    'severity' => $severity,
                    'message' => "Services removed ({$removed}) exceeds threshold ({$thresholds['max_services_removed']})",
                    'count' => $removed,
                ];
            }
        }

        // Check inconsistencies
        if (isset($thresholds['max_inconsistencies'])) {
            $total = $drift['inconsistencies']['total_current'] ?? 0;
            if ($total > $thresholds['max_inconsistencies']) {
                $severity = $this->determineSeverity($total, $thresholds['max_inconsistencies']);
                $maxSeverity = $this->maxSeverity($maxSeverity, $severity);
                $alerts[] = [
                    'type' => 'inconsistencies',
                    'severity' => $severity,
                    'message' => "Inconsistencies ({$total}) exceeds threshold ({$thresholds['max_inconsistencies']})",
                    'count' => $total,
                ];
            }
        }

        // Check architectural violations
        if (isset($thresholds['max_architectural_violations'])) {
            $total = $drift['violations']['total_current'] ?? 0;
            if ($total > $thresholds['max_architectural_violations']) {
                $severity = $this->determineSeverity($total, $thresholds['max_architectural_violations']);
                $maxSeverity = $this->maxSeverity($maxSeverity, $severity);
                $alerts[] = [
                    'type' => 'architectural_violations',
                    'severity' => $severity,
                    'message' => "Architectural violations ({$total}) exceeds threshold ({$thresholds['max_architectural_violations']})",
                    'count' => $total,
                ];
            }
        }

        // Check blockers
        foreach ($blockers as $blocker) {
            $type = $blocker['type'] ?? '';
            $condition = $blocker['condition'] ?? '';

            if ($type === 'services_removed' && $condition === 'any') {
                $removed = count($drift['services']['removed'] ?? []);
                if ($removed > 0) {
                    $policyViolations[] = [
                        'type' => $type,
                        'message' => "Blocker: Any service removal is not allowed ({$removed} removed)",
                        'severity' => 'CRITICAL',
                    ];
                    $maxSeverity = 'CRITICAL';
                }
            }

            if ($type === 'violations_increased' && $condition === 'any') {
                $increased = ($drift['violations']['delta'] ?? 0) > 0;
                if ($increased) {
                    $policyViolations[] = [
                        'type' => $type,
                        'message' => "Blocker: Architectural violations increased",
                        'severity' => 'CRITICAL',
                    ];
                    $maxSeverity = 'CRITICAL';
                }
            }
        }

        $passed = empty($policyViolations) && $maxSeverity !== 'CRITICAL';

        return [
            'passed' => $passed,
            'severity' => $maxSeverity,
            'alerts' => $alerts,
            'violations' => $policyViolations,
        ];
    }

    private function determineSeverity(int $actual, int $threshold): string
    {
        $ratio = $threshold > 0 ? $actual / $threshold : 0;

        if ($ratio >= 3.0) {
            return 'CRITICAL';
        }
        if ($ratio >= 2.0) {
            return 'HIGH';
        }
        if ($ratio >= 1.5) {
            return 'MEDIUM';
        }
        return 'LOW';
    }

    private function maxSeverity(string $a, string $b): string
    {
        $levels = ['LOW' => 1, 'MEDIUM' => 2, 'HIGH' => 3, 'CRITICAL' => 4];
        $aLevel = $levels[$a] ?? 0;
        $bLevel = $levels[$b] ?? 0;

        return $aLevel >= $bLevel ? $a : $b;
    }
}
