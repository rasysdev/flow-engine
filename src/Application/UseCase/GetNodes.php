<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\Application\DTO\NodeDTO;
use FlowEngine\Domain\Contracts\FlowRepository;

/**
 * Retorna todos os nodes públicos do Flow.
 * 
 * Use case simples que delega para FlowQuery.
 */
final class GetNodes
{
    public function __construct(
        private FlowRepository $repository
    ) {
    }

    /**
     * Retorna nodes públicos como DTOs.
     * 
     * @return NodeDTO[]
     */
    public function execute(): array
    {
        $this->repository->analyze();

        $nodes = $this->repository
            ->getFlow()
            ->query()
            ->publicNodes()
            ->all();

        return array_map(
            static fn(\FlowEngine\Domain\Flow\Node $node): NodeDTO => NodeDTO::fromNode($node),
            $nodes
        );
    }
}
