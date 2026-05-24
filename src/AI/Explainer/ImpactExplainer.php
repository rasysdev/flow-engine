<?php
namespace FlowEngine\AI\Explainer;

use FlowEngine\AI\Context\ImpactContext;

final class ImpactExplainer
{
    public function explain(ImpactContext $ctx): string
    {
        return sprintf(
            "Upstream dependencies: %d. Downstream dependents: %d.",
            count($ctx->upstream),
            count($ctx->downstream)
        );
    }
}
