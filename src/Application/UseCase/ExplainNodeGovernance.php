<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\Application\Visibility\NodeVisibilityResolver;
use FlowEngine\Application\Visibility\VisibilityExplanationBuilder;
use FlowEngine\Application\Visibility\VisibilityExplanation;
use LogicException;

final class ExplainNodeGovernance
{
    public function __construct(
        private GetNodeById $getNodeById,
        private NodeVisibilityResolver $resolver,
        private VisibilityExplanationBuilder $builder
    ) {
    }

    public function execute(string $nodeId): VisibilityExplanation
    {
        $node = $this->getNodeById->execute($nodeId);

        if (!$node) {
            throw new LogicException("Node not found: {$nodeId}");
        }

        // Resolve governança (determinístico, read-only)
        $this->resolver->resolve($node);

        // Ler report e construir explicação
        return $this->builder->build(
            $node->id(),
            $this->resolver->report()
        );
    }
}
