<?php

namespace FlowEngine\Application\AppMap;

use FlowEngine\Domain\Contracts\Flow;

final class NodeLocator
{
    /**
     * Best-effort mapping from file+line to a node ID:
     * pick the node in the same file with the greatest (line <= targetLine).
     */
    public function locate(Flow $flow, string $file, int $line): ?string
    {
        $bestId = null;
        $bestLine = -1;

        foreach ($flow->nodes() as $node) {
            $nodeFile = $node->file();
            $nodeLine = $node->line();

            if ($nodeLine === null) {
                continue;
            }

            if ($nodeFile !== $file) {
                continue;
            }

            if ($nodeLine <= $line && $nodeLine > $bestLine) {
                $bestLine = $nodeLine;
                $bestId = $node->id();
            }
        }

        return $bestId;
    }
}

