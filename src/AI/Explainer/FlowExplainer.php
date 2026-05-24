<?php
namespace FlowEngine\AI\Explainer;

use FlowEngine\AI\Context\FlowContext;

final class FlowExplainer
{
    public function explain(FlowContext $ctx): string
    {
        return sprintf(
            "The flow contains %d nodes and %d edges. It represents static execution relationships.",
            count($ctx->nodes),
            count($ctx->edges)
        );
    }
}
