<?php

namespace FlowEngine\Application\DTO;

/**
 * Single refactoring action in a plan.
 *
 * @api
 */
final readonly class RefactorStepDTO
{
    /**
     * @param int $order Step sequence number
     * @param string $action Brief imperative verb phrase
     * @param string $target Class::method being refactored
     * @param string $rationale Why this step is needed
     * @param string $priority LOW/MEDIUM/HIGH/CRITICAL
     * @param string[] $affectedNodes Nodes affected by this step
     * @param string[] $tests Test files to verify this step
     */
    public function __construct(
        public int $order,
        public string $action,
        public string $target,
        public string $rationale,
        public string $priority,
        public array $affectedNodes,
        public array $tests
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'order' => $this->order,
            'action' => $this->action,
            'target' => $this->target,
            'rationale' => $this->rationale,
            'priority' => $this->priority,
            'affectedNodes' => $this->affectedNodes,
            'tests' => $this->tests,
        ];
    }
}
