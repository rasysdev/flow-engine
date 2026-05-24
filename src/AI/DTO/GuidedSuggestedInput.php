<?php

namespace FlowEngine\AI\DTO;

final class GuidedSuggestedInput
{
    public function __construct(
        public readonly int $position,
        public readonly mixed $value,
        public readonly string $reason
    ) {
    }
}
