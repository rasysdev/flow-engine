<?php

namespace FlowEngine\Application\Visibility;

use FlowEngine\Domain\Flow\Flow;
use FlowEngine\Domain\Flow\Node;

final class FlowVisibilityMaterializer
{
    public function __construct(
        private NodeVisibilityResolver $resolver
    ) {
    }

    public function materialize(Flow $flow): Flow
    {
        $nodes = [];

        foreach ($flow->nodes() as $node) {
            $visibility = $this->resolver->resolve($node);

            if ($visibility) {
                $nodes[] = $node->withVisibility($visibility);
            } else {
                $nodes[] = $node;
            }
        }

        return new Flow(
            $nodes,
            $flow->edges() // preserva grafo
        );
    }
}
