<?php

namespace FlowEngine\Application\DTO;

final readonly class InterpretationResultDTO
{
    /**
     * @param string $type Interpretation type (cycles, hotspots, impact, violations)
     * @param string $interpretation LLM-generated interpretation text
     * @param int $tokensUsed Tokens consumed by the LLM call
     * @param array<string, mixed> $context The context sent to the LLM
     * @param array<string, mixed> $metadata Additional metadata
     */
    public function __construct(
        public string $type,
        public string $interpretation,
        public int $tokensUsed,
        public array $context,
        public array $metadata = []
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'interpretation' => $this->interpretation,
            'tokensUsed' => $this->tokensUsed,
            'context' => $this->context,
            'metadata' => $this->metadata,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }
}
