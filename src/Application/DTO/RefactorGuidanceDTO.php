<?php

namespace FlowEngine\Application\DTO;

/**
 * Detailed step-by-step guidance for executing a single refactoring step.
 *
 * @api
 */
final readonly class RefactorGuidanceDTO
{
    /**
     * @param string $nodeId Target node identifier
     * @param int $stepOrder Step sequence number
     * @param string $stepAction Brief imperative verb phrase from the plan
     * @param string $stepTarget Class::method being refactored
     * @param string[] $actionableInstructions Concrete steps to execute
     * @param string[] $codePatterns Code patterns or snippets to apply
     * @param string[] $warningsToAvoid Common pitfalls and anti-patterns
     * @param string[] $verificationChecklist How to confirm the step was applied
     * @param string $estimatedEffort e.g. "30 minutes", "1 day"
     * @param array<string, mixed> $metadata Additional data (tokensUsed, llmConfigured, etc.)
     */
    public function __construct(
        public string $nodeId,
        public int $stepOrder,
        public string $stepAction,
        public string $stepTarget,
        public array $actionableInstructions,
        public array $codePatterns,
        public array $warningsToAvoid,
        public array $verificationChecklist,
        public string $estimatedEffort,
        public array $metadata = []
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
            'stepAction' => $this->stepAction,
            'stepTarget' => $this->stepTarget,
            'actionableInstructions' => $this->actionableInstructions,
            'codePatterns' => $this->codePatterns,
            'warningsToAvoid' => $this->warningsToAvoid,
            'verificationChecklist' => $this->verificationChecklist,
            'estimatedEffort' => $this->estimatedEffort,
            'metadata' => $this->metadata,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }
}
