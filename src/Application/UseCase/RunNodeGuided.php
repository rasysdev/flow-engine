<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\Application\DTO\RunNodeGuidedResult;
use FlowEngine\Application\Port\NodeInputsProvider;
use FlowEngine\Domain\Execution\ExecutionContext;

use LogicException;

final class RunNodeGuided
{
    public function __construct(
        private GetNodeById $getNodeById,
        private NodeInputsProvider $getNodeInputs,
        private ResolveGuidedArguments $resolveArguments,
        private ExecuteNode $executeNode
    ) {
    }

    public function execute(string $nodeId, array $rawArgs): RunNodeGuidedResult
    {
        $node = $this->getNodeById->execute($nodeId);

        if (!$node) {
            throw new LogicException("Node not found: {$nodeId}");
        }

        $inputsResult = $this->getNodeInputs->execute($node);

        $resolved = $this->resolveArguments->execute(
            $node,
            $inputsResult->inputs,
            $rawArgs
        );

        $execution = $this->executeNode->execute(
            $node,
            $resolved->args,
            ExecutionContext::guided("guided execution of {$nodeId}")
        );

        return new RunNodeGuidedResult(
            nodeId: $nodeId,
            inputs: $resolved->inputs,
            result: $execution
        );
    }
}
