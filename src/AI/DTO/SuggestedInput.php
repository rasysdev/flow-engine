<?php

namespace FlowEngine\AI\DTO;

final class SuggestedInput
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly mixed $suggestedValue,
        public readonly string $reason
    ) {
        if ($reason === '') {
            throw new \InvalidArgumentException('Suggestion reason must not be empty.');
        }
    }
}
