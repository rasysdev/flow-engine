<?php

use PHPUnit\Framework\TestCase;
use FlowEngine\Infrastructure\LLM\AnthropicConfig;
use FlowEngine\Infrastructure\LLM\AnthropicProvider;
use FlowEngine\AI\LLM\LLMRequest;
use FlowEngine\AI\LLM\LLMException;

final class AnthropicProviderTest extends TestCase
{
    public function test_is_configured_with_api_key(): void
    {
        $config = new AnthropicConfig(apiKey: 'sk-test-key');
        $provider = new AnthropicProvider($config);

        $this->assertTrue($provider->isConfigured());
    }

    public function test_is_not_configured_with_empty_key(): void
    {
        $config = new AnthropicConfig(apiKey: '');
        $provider = new AnthropicProvider($config);

        $this->assertFalse($provider->isConfigured());
    }

    public function test_build_payload_structure(): void
    {
        $config = new AnthropicConfig(apiKey: 'sk-test', model: 'claude-sonnet-4-5-20250929');
        $provider = new AnthropicProvider($config);

        $request = new LLMRequest(
            systemPrompt: 'You are a helper.',
            userPrompt: 'What are the hotspots?',
            context: ['## Metrics', '## Complexity'],
            maxTokens: 2048
        );

        $payload = $provider->buildPayload($request);

        $this->assertSame('claude-sonnet-4-5-20250929', $payload['model']);
        $this->assertSame(2048, $payload['max_tokens']);
        $this->assertSame('You are a helper.', $payload['system']);
        $this->assertCount(1, $payload['messages']);
        $this->assertSame('user', $payload['messages'][0]['role']);
        $this->assertStringContainsString('## Metrics', $payload['messages'][0]['content']);
        $this->assertStringContainsString('What are the hotspots?', $payload['messages'][0]['content']);
    }

    public function test_build_payload_without_context(): void
    {
        $config = new AnthropicConfig(apiKey: 'sk-test');
        $provider = new AnthropicProvider($config);

        $request = new LLMRequest(
            systemPrompt: 'system',
            userPrompt: 'question'
        );

        $payload = $provider->buildPayload($request);

        $this->assertSame('question', $payload['messages'][0]['content']);
    }

    public function test_parse_successful_response(): void
    {
        $data = [
            'content' => [
                ['type' => 'text', 'text' => 'The main hotspot is UserService.'],
            ],
            'model' => 'claude-sonnet-4-5-20250929',
            'stop_reason' => 'end_turn',
            'usage' => [
                'input_tokens' => 100,
                'output_tokens' => 50,
            ],
        ];

        $response = AnthropicProvider::parseResponse($data);

        $this->assertSame('The main hotspot is UserService.', $response->content);
        $this->assertSame(150, $response->tokensUsed);
        $this->assertSame('anthropic', $response->metadata['provider']);
        $this->assertSame(100, $response->metadata['input_tokens']);
        $this->assertSame(50, $response->metadata['output_tokens']);
    }

    public function test_parse_error_response_throws_exception(): void
    {
        $data = [
            'error' => [
                'message' => 'Invalid API key',
            ],
        ];

        $this->expectException(LLMException::class);
        $this->expectExceptionMessage('Invalid API key');

        AnthropicProvider::parseResponse($data);
    }

    public function test_config_defaults(): void
    {
        $config = new AnthropicConfig(apiKey: 'sk-test');

        $this->assertSame('claude-sonnet-4-5-20250929', $config->model);
        $this->assertSame('https://api.anthropic.com/v1/messages', $config->endpoint);
    }
}
