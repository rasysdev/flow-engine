<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\Domain\Contracts\FlowRepository;

final class AnalyzeProject
{
    public function __construct(
        private FlowRepository $repository
    ) {
    }

    public function execute(): void
    {
        $this->repository->analyze();
    }
}
