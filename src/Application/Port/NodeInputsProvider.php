<?php

namespace FlowEngine\Application\Port;

use FlowEngine\Application\DTO\NodeInputs;
use FlowEngine\Domain\Flow\Node;

interface NodeInputsProvider
{
    public function execute(Node $node): NodeInputs;
}
