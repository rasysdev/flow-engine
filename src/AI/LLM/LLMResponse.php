<?php

namespace FlowEngine\AI\LLM;

final readonly class LLMResponse
{
    /**
     * @param string $content
     * @param int $tokensUsed
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $content,
        public int $tokensUsed = 0,
        public array $metadata = []
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'tokensUsed' => $this->tokensUsed,
            'metadata' => $this->metadata,
        ];
    }
}
