<?php

namespace FlowEngine\Application\DTO;

final class NodeInputDefinition
{
    public function __construct(
        public string $name,
        public string $type,
        public bool $required,
        public mixed $default
    ) {
    }
}
