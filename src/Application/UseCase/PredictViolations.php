<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\Application\DTO\PredictViolationsDTO;
use FlowEngine\Domain\Contracts\SnapshotStorePort;

/**
 * Predicts whether the current codebase state introduces architecture violations,
 * optionally comparing against a saved baseline snapshot.
 *
 * Designed for CI gates: `shouldFail` in the returned DTO drives the exit code.
 *
 * Fail-on policies:
 *   'any'      — gate fails if any violation exists (strictest; useful for greenfield projects)
 *   'new'      — gate fails only if violations not present in the baseline appear (recommended
 *                for legacy projects that already carry known violations)
 *   'critical' — gate fails only on CRITICAL severity violations (Domain → Infrastructure /
 *                Domain → Application); HIGH violations are reported but tolerated
 *
 * When no baseline is provided, 'new' behaves the same as 'any'.
 */
final class PredictViolations
{
    public function __construct(
        private AnalyzeArchitecture $analyzeArchitecture,
        private SnapshotStorePort   $snapshotStore,
    ) {}

    /**
     * @param string      $failOn        'any' | 'new' | 'critical'
     * @param string|null $baselineLabel Snapshot label to diff against, or null for absolute check
     */
    public function execute(string $failOn = 'any', ?string $baselineLabel = null): PredictViolationsDTO
    {
        // Current state
        $report            = $this->analyzeArchitecture->execute();
        $currentViolations = $report->violations;

        // Baseline comparison
        $newViolations      = [];
        $resolvedViolations = [];

        if ($baselineLabel !== null) {
            $snapshot           = $this->snapshotStore->load($baselineLabel);
            $baselineViolations = $snapshot['architecture']['violations'] ?? [];

            $currentKeys  = $this->violationKeys($currentViolations);
            $baselineKeys = $this->violationKeys($baselineViolations);

            // New = present in current but absent from baseline
            $newKeys       = array_diff($currentKeys, $baselineKeys);
            $newViolations = array_values(array_filter(
                $currentViolations,
                fn(array $v) => in_array($this->key($v), $newKeys, true)
            ));

            // Resolved = present in baseline but absent from current
            $resolvedKeys       = array_diff($baselineKeys, $currentKeys);
            $resolvedViolations = array_values(array_filter(
                $baselineViolations,
                fn(array $v) => in_array($this->key($v), $resolvedKeys, true)
            ));
        } else {
            // Without a baseline every current violation is treated as "new"
            $newViolations = $currentViolations;
        }

        $shouldFail = $this->evaluate($failOn, $currentViolations, $newViolations);

        return new PredictViolationsDTO(
            currentViolations:  $currentViolations,
            newViolations:      $newViolations,
            resolvedViolations: $resolvedViolations,
            totalCurrent:       count($currentViolations),
            totalNew:           count($newViolations),
            totalResolved:      count($resolvedViolations),
            hasBaseline:        $baselineLabel !== null,
            baselineLabel:      $baselineLabel,
            shouldFail:         $shouldFail,
            failOn:             $failOn,
            isClean:            count($currentViolations) === 0,
        );
    }

    // -------------------------------------------------------------------------

    private function evaluate(string $failOn, array $current, array $newViolations): bool
    {
        return match ($failOn) {
            'any'      => count($current) > 0,
            'new'      => count($newViolations) > 0,
            'critical' => count(array_filter($current, fn(array $v) => ($v['severity'] ?? '') === 'CRITICAL')) > 0,
            default    => false,
        };
    }

    /** @return string[] */
    private function violationKeys(array $violations): array
    {
        return array_map(fn(array $v) => $this->key($v), $violations);
    }

    private function key(array $v): string
    {
        return ($v['from'] ?? '') . '->' . ($v['to'] ?? '');
    }
}
