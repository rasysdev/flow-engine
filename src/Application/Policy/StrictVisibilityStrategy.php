<?php

namespace FlowEngine\Application\Policy;

use FlowEngine\Domain\Node\NodeVisibility;
use LogicException;

final class StrictVisibilityStrategy implements VisibilityCompositionStrategy
{
    /**
     * @param VisibilityDecisionResult[] $results
     */
    public function compose(array $results): ?NodeVisibility
    {
        $decision = VisibilityDecision::ABSTAIN;
        $final = null;

        foreach ($results as $r) {
            if ($r->decision === VisibilityDecision::ABSTAIN) {
                continue;
            }

            if ($decision !== VisibilityDecision::ABSTAIN && $decision !== $r->decision) {
                throw new LogicException('Conflicting visibility policies detected.');
            }

            $decision = $r->decision;
            $final = $r->visibility;
        }

        return $final;
    }
}
