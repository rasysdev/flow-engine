<?php

namespace FlowEngine\Application\DTO;

/**
 * Blocking issue that must be resolved before refactoring.
 *
 * @api
 */
final readonly class RefactorPrerequisiteDTO
{
    /**
     * @param string $type cycle|violation|orphan
     * @param string $description What blocks refactoring
     * @param string[] $affectedNodes Nodes involved in blocker
     * @param string $severity LOW/MEDIUM/HIGH/CRITICAL
     * @param string $recommendation How to resolve
     */
    public function __construct(
        public string $type,
        public string $description,
        public array $affectedNodes,
        public string $severity,
        public string $recommendation
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'description' => $this->description,
            'affectedNodes' => $this->affectedNodes,
            'severity' => $this->severity,
            'recommendation' => $this->recommendation,
        ];
    }
}
