<?php

namespace FlowEngine\Application\DTO;

/**
 * Diff between two analysis snapshots.
 *
 * @api
 */
final readonly class SnapshotComparisonDTO
{
    /**
     * @param string $baselineLabel Label of the baseline snapshot
     * @param string $currentLabel Label of the current snapshot
     * @param array<string, array> $metrics Metric deltas [key => [old, new, delta]]
     * @param array{new: array, resolved: array, totalDelta: int} $cycles Cycle changes
     * @param array{new: array, resolved: array, totalDelta: int} $violations Violation changes
     * @param array{new: array, resolved: array, totalDelta: int} $orphans Orphan changes
     * @param array{improved: array, degraded: array, avgDelta: float} $complexity Complexity changes
     * @param array{improved: int, degraded: int, unchanged: int} $summary Overall summary
     */
    public function __construct(
        public string $baselineLabel,
        public string $currentLabel,
        public array $metrics,
        public array $cycles,
        public array $violations,
        public array $orphans,
        public array $complexity,
        public array $summary
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'baselineLabel' => $this->baselineLabel,
            'currentLabel' => $this->currentLabel,
            'metrics' => $this->metrics,
            'cycles' => $this->cycles,
            'violations' => $this->violations,
            'orphans' => $this->orphans,
            'complexity' => $this->complexity,
            'summary' => $this->summary,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }
}
