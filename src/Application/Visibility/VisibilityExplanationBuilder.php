<?php

namespace FlowEngine\Application\Visibility;

use FlowEngine\Application\Policy\VisibilityResolutionReport;

final class VisibilityExplanationBuilder
{
    public function build(
        string $nodeId,
        VisibilityResolutionReport $report
    ): VisibilityExplanation {
        $items = [];
        $order = 1;

        foreach ($report->results() as $result) {
            $items[] = new VisibilityExplanationItem(
                $order++,
                $result->policyClass,
                $result->source,
                $result->decision
            );
        }

        return new VisibilityExplanation(
            $nodeId,
            $report->finalDecision(),
            $items
        );
    }
}
