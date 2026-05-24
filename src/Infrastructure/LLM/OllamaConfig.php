<?php

namespace FlowEngine\Infrastructure\LLM;

final readonly class OllamaConfig
{
    public function __construct(
        public string $host,
        public string $model,
        public string $endpoint
    ) {
    }

    public static function fromEnvironment(): ?self
    {
        $host = getenv('OLLAMA_HOST');
        $model = getenv('OLLAMA_MODEL');

        if (!$host || trim($host) === '' || !$model || trim($model) === '') {
            return null;
        }

        $host = rtrim($host, '/');

        return new self(
            host: $host,
            model: $model,
            endpoint: $host . '/api/chat'
        );
    }
}

