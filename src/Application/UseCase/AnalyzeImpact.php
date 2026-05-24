<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\Domain\Contracts\FlowRepository;
use LogicException;

final class AnalyzeImpact
{
    public function __construct(
        private FlowRepository $repository
    ) {
    }

    public function execute(string $nodeId): array
    {
        $flow = $this->repository->getFlow();

        if (!$flow->node($nodeId)) {
            throw new LogicException("Node not found: {$nodeId}");
        }

        $upstream = [];
        $downstream = [];

        foreach ($flow->edges() as $edge) {
            if ($edge->to() === $nodeId) {
                $upstream[] = $edge->from();
            }

            if ($edge->from() === $nodeId) {
                $downstream[] = $edge->to();
            }
        }

        return [
            'node' => $nodeId,
            'impact' => [
                'upstream' => array_values(array_unique($upstream)),
                'downstream' => array_values(array_unique($downstream)),
            ],
        ];
    }
}
