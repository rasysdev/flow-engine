<?php

namespace FlowEngine\Application\DTO;

/**
 * Result of validating whether a refactoring step was correctly applied.
 *
 * @api
 */
final readonly class RefactorValidationDTO
{
    /**
     * @param string $nodeId Target node identifier
     * @param int $stepOrder Step sequence number
     * @param bool $passed Whether the step appears to have been applied correctly
     * @param string[] $findings Specific observations (improvements, regressions, unchanged metrics)
     * @param array{fanIn: int, fanOut: int, blastRadius: int} $currentMetrics Current graph metrics
     * @param array{fanIn: int, fanOut: int, blastRadius: int} $baselineMetrics Metrics at plan-creation time
     * @param string $recommendation Next suggested action
     */
    public function __construct(
        public string $nodeId,
        public int $stepOrder,
        public bool $passed,
        public array $findings,
        public array $currentMetrics,
        public array $baselineMetrics,
        public string $recommendation
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'nodeId' => $this->nodeId,
            'stepOrder' => $this->stepOrder,
            'passed' => $this->passed,
            'findings' => $this->findings,
            'currentMetrics' => $this->currentMetrics,
            'baselineMetrics' => $this->baselineMetrics,
            'recommendation' => $this->recommendation,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }
}
