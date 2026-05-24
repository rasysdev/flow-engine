<?php

use PHPUnit\Framework\TestCase;
use FlowEngine\AI\LLM\NullLLMProvider;
use FlowEngine\AI\LLM\LLMRequest;

final class NullLLMProviderTest extends TestCase
{
    public function test_is_not_configured(): void
    {
        $provider = new NullLLMProvider();
        $this->assertFalse($provider->isConfigured());
    }

    public function test_send_returns_informative_message(): void
    {
        $provider = new NullLLMProvider();
        $request = new LLMRequest(
            systemPrompt: 'system',
            userPrompt: 'question'
        );

        $response = $provider->send($request);

        $this->assertStringContainsString('not configured', $response->content);
        $this->assertSame(0, $response->tokensUsed);
        $this->assertSame('null', $response->metadata['provider']);
    }

    public function test_response_to_array(): void
    {
        $provider = new NullLLMProvider();
        $request = new LLMRequest(
            systemPrompt: 'system',
            userPrompt: 'question'
        );

        $response = $provider->send($request);
        $array = $response->toArray();

        $this->assertArrayHasKey('content', $array);
        $this->assertArrayHasKey('tokensUsed', $array);
        $this->assertArrayHasKey('metadata', $array);
    }
}
