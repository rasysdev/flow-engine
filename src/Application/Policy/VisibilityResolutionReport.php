<?php

namespace FlowEngine\Application\Policy;

final class VisibilityResolutionReport
{
    /** @var VisibilityDecisionResult[] */
    private array $results = [];

    public function add(VisibilityDecisionResult $result): void
    {
        $this->results[] = $result;
    }

    /**
     * @return VisibilityDecisionResult[]
     */
    public function results(): array
    {
        return $this->results;
    }

    public function finalDecision(): VisibilityDecision
    {
        foreach ($this->results as $r) {
            if ($r->decision !== VisibilityDecision::ABSTAIN) {
                return $r->decision;
            }
        }

        return VisibilityDecision::ABSTAIN;
    }
}
