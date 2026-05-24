<?php

namespace FlowEngine\AI\LLM;

final readonly class LLMRequest
{
    /**
     * @param string $systemPrompt
     * @param string $userPrompt
     * @param string[] $context
     * @param int $maxTokens
     */
    public function __construct(
        public string $systemPrompt,
        public string $userPrompt,
        public array $context = [],
        public int $maxTokens = 4096
    ) {
    }
}
