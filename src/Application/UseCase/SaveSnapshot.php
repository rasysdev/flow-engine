<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\Domain\Contracts\SnapshotStorePort;

final class SaveSnapshot
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

    public function execute(string $label): void
    {
        $reports = [
            'metrics' => $this->analyzeMetrics->execute()->toArray(),
            'complexity' => $this->analyzeComplexity->execute()->toArray(),
            'cycles' => $this->analyzeCycles->execute()->toArray(),
            'architecture' => $this->analyzeArchitecture->execute()->toArray(),
            'orphans' => $this->analyzeOrphans->execute()->toArray(),
        ];

        $this->snapshotStore->save($label, $reports);
    }
}
