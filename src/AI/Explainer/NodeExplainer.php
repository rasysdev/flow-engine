<?php
namespace FlowEngine\AI\Explainer;

use FlowEngine\AI\Context\NodeContext;

final class NodeExplainer
{
    public function explain(NodeContext $ctx): string
    {
        return "Node {$ctx->id} corresponds to {$ctx->class}::{$ctx->method} and is {$ctx->visibility}.";
    }
}
