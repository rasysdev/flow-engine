<?php

namespace FlowEngine\Domain\Contracts;

use FlowEngine\Domain\Contracts\Flow as FlowContract;
use FlowEngine\Domain\Flow\Node;

interface FlowRepository
{
    public function analyze(): void;

    /** @return Node[] */
    public function getNodes(): array;

    public function getNode(string $id): Node;

    /** Returns null if the node does not exist (no exception). */
    public function findNode(string $id): ?Node;

    /**
     * Exposição explícita do grafo analisado
     */
    public function getFlow(): FlowContract;
}
