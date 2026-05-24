<?php

namespace FlowEngine\Domain\Contracts;

use FlowEngine\Domain\Flow\FlowQuery;
use FlowEngine\Domain\Flow\Node;
use FlowEngine\Domain\Flow\Edge;
use FlowEngine\Domain\Flow\SymbolIndex;

interface Flow
{
    /** @return Node[] */
    public function nodes(): array;

    /** @return Edge[] */
    public function edges(): array;

    public function node(string $id): ?Node;
    public function query(): FlowQuery;
    public function nodeCount(): int;
    public function edgeCount(): int;
    public function symbols(): SymbolIndex;
}
