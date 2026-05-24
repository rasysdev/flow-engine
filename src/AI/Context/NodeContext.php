<?php
namespace FlowEngine\AI\Context;

final class NodeContext
{
    public function __construct(
        public readonly string $id,
        public readonly string $class,
        public readonly string $method,
        public readonly string $visibility
    ) {
    }
}
