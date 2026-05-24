<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\Domain\Contracts\FlowRepository;
use FlowEngine\Domain\Flow\Node;

final class GetNodeById
{
    public function __construct(
        private FlowRepository $repository
    ) {
    }

    public function execute(string $id): ?Node
    {
        return $this->repository->findNode($id);
    }
}
