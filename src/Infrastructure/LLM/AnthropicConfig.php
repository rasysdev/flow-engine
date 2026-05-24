<?php

namespace FlowEngine\Infrastructure\LLM;

final readonly class AnthropicConfig
{
    public function __construct(
        public string $apiKey,
        public string $model = 'claude-sonnet-4-5-20250929',
        public string $endpoint = 'https://api.anthropic.com/v1/messages'
    ) {
    }

    public static function fromEnvironment(): ?self
    {
        $apiKey = getenv('ANTHROPIC_API_KEY');

        if (!$apiKey || $apiKey === '') {
            return null;
        }

        $model = getenv('ANTHROPIC_MODEL') ?: 'claude-sonnet-4-5-20250929';

        return new self(
            apiKey: $apiKey,
            model: $model
        );
    }
}
