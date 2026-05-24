<?php

namespace FlowEngine\Infrastructure\LLM;

final readonly class OpenAIConfig
{
    public function __construct(
        public string $apiKey,
        public string $model = 'gpt-4o-mini',
        public string $endpoint = 'https://api.openai.com/v1/chat/completions'
    ) {
    }

    public static function fromEnvironment(): ?self
    {
        $apiKey = getenv('OPENAI_API_KEY');

        if (!$apiKey || $apiKey === '') {
            return null;
        }

        $model = getenv('OPENAI_MODEL') ?: 'gpt-4o-mini';
        $baseUrl = getenv('OPENAI_BASE_URL') ?: '';

        $endpoint = $baseUrl !== ''
            ? rtrim($baseUrl, '/') . '/v1/chat/completions'
            : 'https://api.openai.com/v1/chat/completions';

        return new self(
            apiKey: $apiKey,
            model: $model,
            endpoint: $endpoint
        );
    }
}

