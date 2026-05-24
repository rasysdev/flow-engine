<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\Application\Port\NodeInputsProvider;
use FlowEngine\Application\DTO\NodeInputDefinition;
use FlowEngine\Application\DTO\NodeInputs;
use FlowEngine\Domain\Contracts\InputIntrospector;
use FlowEngine\Domain\Flow\Node;

final class GetNodeInputs implements NodeInputsProvider
{
    public function __construct(
        private InputIntrospector $introspector
    ) {
    }

    public function execute(Node $node): NodeInputs
    {
        $meta = $this->introspector->introspect($node);

        $inputs = array_map(
            fn (array $input) => new NodeInputDefinition(
                $input['name'],
                $input['type'],
                $input['required'],
                $input['default']
            ),
            $meta['inputs']
        );

        return new NodeInputs(
            $inputs,
            $meta['return']
        );
    }
}
