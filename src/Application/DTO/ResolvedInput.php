<?php

namespace FlowEngine\Application\DTO;

final class ResolvedInput
{
    public function __construct(
        public string $name,
        public string $type,
        public mixed $value
    ) {
    }
}
