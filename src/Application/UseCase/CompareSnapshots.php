<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\Application\DTO\SnapshotComparisonDTO;
use FlowEngine\Domain\Analysis\SnapshotComparator;
use FlowEngine\Domain\Contracts\SnapshotStorePort;

final class CompareSnapshots
{
    public function __construct(
        private AnalyzeMetrics $analyzeMetrics,
        private AnalyzeComplexity $analyzeComplexity,
        private AnalyzeCycles $analyzeCycles,
        private AnalyzeArchitecture $analyzeArchitecture,
        private AnalyzeOrphans $analyzeOrphans,
        private SnapshotStorePort $snapshotStore
    ) {
    }

    public function execute(string $baselineLabel): SnapshotComparisonDTO
    {
        $baseline = $this->snapshotStore->load($baselineLabel);

        $current = [
            'metrics' => $this->analyzeMetrics->execute()->toArray(),
            'complexity' => $this->analyzeComplexity->execute()->toArray(),
            'cycles' => $this->analyzeCycles->execute()->toArray(),
            'architecture' => $this->analyzeArchitecture->execute()->toArray(),
            'orphans' => $this->analyzeOrphans->execute()->toArray(),
        ];

        $comparator = new SnapshotComparator();
        $diff = $comparator->compare($baseline, $current);

        return new SnapshotComparisonDTO(
            baselineLabel: $baselineLabel,
            currentLabel: 'current',
            metrics: $diff['metrics'],
            cycles: $diff['cycles'],
            violations: $diff['violations'],
            orphans: $diff['orphans'],
            complexity: $diff['complexity'],
            summary: $diff['summary']
        );
    }
}
