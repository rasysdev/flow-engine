<?php

namespace FlowEngine\AI\LLM;

interface LLMProvider
{
    public function send(LLMRequest $request): LLMResponse;

    public function isConfigured(): bool;
}
