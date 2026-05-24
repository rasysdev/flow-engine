<?php

namespace FlowEngine\Application\DTO;

/**
 * Single remediation proposal that requires explicit human approval.
 *
 * @api
 */
final readonly class RemediationProposalDTO
{
    /**
     * @param string $id Stable proposal identifier
     * @param string $category architecture|hotspot
     * @param string $priority P1|P2|P3
     * @param string $target Target node or dependency relation
     * @param string $title Short proposal title
     * @param string $reason Why this remediation was proposed
     * @param string[] $actions Suggested implementation steps
     * @param array<string, mixed> $expectedImpact Expected measurable impact
     * @param bool $requiresApproval Human-in-the-loop guard
     */
    public function __construct(
        public string $id,
        public string $category,
        public string $priority,
        public string $target,
        public string $title,
        public string $reason,
        public array $actions,
        public array $expectedImpact,
        public bool $requiresApproval = true
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'priority' => $this->priority,
            'target' => $this->target,
            'title' => $this->title,
            'reason' => $this->reason,
            'actions' => $this->actions,
            'expectedImpact' => $this->expectedImpact,
            'requiresApproval' => $this->requiresApproval,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }
}

