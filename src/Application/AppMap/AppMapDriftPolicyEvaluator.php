<?php

namespace FlowEngine\Application\AppMap;

final class AppMapDriftPolicyEvaluator
{
    /**
     * @param array<string, mixed> $diff Output from AppMapDiffAnalyzer::diff
     * @param array<string, mixed> $policy
     * @return array<string, mixed>
     */
    public function evaluate(array $diff, array $policy): array
    {
        $reasons = [];

        $summary = is_array($diff['summary'] ?? null) ? $diff['summary'] : [];
        $thresholds = is_array($policy['thresholds'] ?? null) ? $policy['thresholds'] : [];

        $this->checkThreshold($summary, $thresholds, 'servicesAdded', $reasons);
        $this->checkThreshold($summary, $thresholds, 'servicesRemoved', $reasons);
        $this->checkThreshold($summary, $thresholds, 'serviceEdgesAdded', $reasons);
        $this->checkThreshold($summary, $thresholds, 'serviceEdgesRemoved', $reasons);
        $this->checkThreshold($summary, $thresholds, 'inconsistenciesAdded', $reasons);
        $this->checkThreshold($summary, $thresholds, 'breakingChanges', $reasons);

        $blockers = is_array($policy['blockers'] ?? null) ? $policy['blockers'] : [];

        $edgeTypes = is_array($blockers['edgeTypes'] ?? null) ? $blockers['edgeTypes'] : [];
        if ($edgeTypes !== []) {
            $addedEdges = is_array($diff['serviceEdges']['addedDetails'] ?? null)
                ? $diff['serviceEdges']['addedDetails']
                : [];

            foreach ($addedEdges as $edge) {
                if (!is_array($edge)) {
                    continue;
                }
                $type = (string) ($edge['type'] ?? '');
                if (in_array($type, $edgeTypes, true)) {
                    $reasons[] = "Blocked edge type added: {$type}";
                }
            }
        }

        $severities = is_array($blockers['inconsistencySeverities'] ?? null)
            ? $blockers['inconsistencySeverities']
            : [];
        if ($severities !== []) {
            $addedInc = is_array($diff['inconsistencies']['addedDetails'] ?? null)
                ? $diff['inconsistencies']['addedDetails']
                : [];

            foreach ($addedInc as $issue) {
                if (!is_array($issue)) {
                    continue;
                }
                $sev = (string) ($issue['severity'] ?? '');
                if (in_array($sev, $severities, true)) {
                    $reasons[] = "Blocked inconsistency severity added: {$sev}";
                }
            }
        }

        if (($blockers['breakingDependencyChanges'] ?? false) === true) {
            $breakingTotal = (int) (($diff['breakingChanges']['total'] ?? null) ?? 0);
            if ($breakingTotal > 0) {
                $reasons[] = "Blocked breaking dependency changes detected: {$breakingTotal}";
            }
        }

        return [
            'passed' => $reasons === [],
            'reasons' => array_values(array_unique($reasons)),
        ];
    }

    /**
     * @param array<string, mixed> $summary
     * @param array<string, mixed> $thresholds
     * @param array<int, string> $reasons
     */
    private function checkThreshold(array $summary, array $thresholds, string $metric, array &$reasons): void
    {
        $key = $metric . 'Max';
        if (!array_key_exists($key, $thresholds)) {
            return;
        }

        $max = (int) $thresholds[$key];
        $actual = (int) ($summary[$metric] ?? 0);

        if ($actual > $max) {
            $reasons[] = "Threshold exceeded: {$metric}={$actual} > {$key}={$max}";
        }
    }
}
