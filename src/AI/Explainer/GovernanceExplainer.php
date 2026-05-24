<?php
namespace FlowEngine\AI\Explainer;

use FlowEngine\AI\Context\VisibilityContext;

final class GovernanceExplainer
{
    public function explain(VisibilityContext $ctx): string
    {
        return "Final decision is {$ctx->finalDecision}. Policies were evaluated in order with recorded sources and decisions.";
    }
}
