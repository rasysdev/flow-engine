<?php

namespace FlowEngine\AI\LLM;

final class NullLLMProvider implements LLMProvider
{
    public function send(LLMRequest $request): LLMResponse
    {
        return new LLMResponse(
            content: 'LLM provider is not configured. Configure one of: ANTHROPIC_API_KEY, OPENAI_API_KEY, or (local) OLLAMA_HOST + OLLAMA_MODEL. You can also force selection with FLOW_ENGINE_LLM_PROVIDER.',
            tokensUsed: 0,
            metadata: ['provider' => 'null']
        );
    }

    public function isConfigured(): bool
    {
        return false;
    }
}
