<?php

namespace FlowEngine\Application\DTO;

/**
 * Tracks progress through a saved refactoring plan's steps.
 *
 * @api
 */
final readonly class RefactorProgressDTO
{
    /**
     * @param string $planLabel The label used to save the plan
     * @param string $nodeId Target node identifier
     * @param int $totalSteps Total number of steps in the plan
     * @param int $completedSteps Number of steps marked done or skipped
     * @param int|null $currentStep The next pending step order, or null if all done
     * @param array<int, array{order: int, action: string, target: string, status: string, completedAt: string|null}> $steps Per-step status
     * @param string $savedAt ISO-style datetime of last progress save
     */
    public function __construct(
        public string $planLabel,
        public string $nodeId,
        public int $totalSteps,
        public int $completedSteps,
        public ?int $currentStep,
        public array $steps,
        public string $savedAt
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'planLabel' => $this->planLabel,
            'nodeId' => $this->nodeId,
            'totalSteps' => $this->totalSteps,
            'completedSteps' => $this->completedSteps,
            'currentStep' => $this->currentStep,
            'steps' => $this->steps,
            'savedAt' => $this->savedAt,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }
}
