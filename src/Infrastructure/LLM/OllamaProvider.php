<?php

namespace FlowEngine\Infrastructure\LLM;

use FlowEngine\AI\LLM\LLMException;
use FlowEngine\AI\LLM\LLMProvider;
use FlowEngine\AI\LLM\LLMRequest;
use FlowEngine\AI\LLM\LLMResponse;

final class OllamaProvider implements LLMProvider
{
    public function __construct(
        private OllamaConfig $config
    ) {
    }

    public function send(LLMRequest $request): LLMResponse
    {
        $payload = $this->buildPayload($request);
        $responseData = $this->executeRequest($payload);

        return self::parseResponse($responseData, $this->config->model);
    }

    public function isConfigured(): bool
    {
        return $this->config->host !== '' && $this->config->model !== '';
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(LLMRequest $request): array
    {
        $userContent = '';

        if ($request->context !== []) {
            $userContent .= implode("\n\n", $request->context) . "\n\n";
        }

        $userContent .= $request->userPrompt;

        return [
            'model' => $this->config->model,
            'stream' => false,
            'messages' => [
                ['role' => 'system', 'content' => $request->systemPrompt],
                ['role' => 'user', 'content' => $userContent],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $responseData
     */
    private static function parseResponse(array $responseData, string $requestedModel): LLMResponse
    {
        if (isset($responseData['error'])) {
            throw new LLMException('Ollama API error: ' . (string) $responseData['error']);
        }

        $content = (string) (($responseData['message']['content'] ?? '') ?: '');

        return new LLMResponse(
            content: $content,
            tokensUsed: 0,
            metadata: [
                'provider' => 'ollama',
                'model' => (string) ($responseData['model'] ?? $requestedModel),
                'done_reason' => (string) ($responseData['done_reason'] ?? ''),
            ]
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function executeRequest(array $payload): array
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);

        $ch = curl_init($this->config->endpoint);

        if ($ch === false) {
            throw new LLMException('Failed to initialize cURL');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 120,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new LLMException('cURL error: ' . $error);
        }

        $data = json_decode($response, true);

        if (!is_array($data)) {
            throw new LLMException('Invalid JSON response from Ollama API');
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $message = isset($data['error']) ? (string) $data['error'] : "HTTP {$httpCode}";
            throw new LLMException('Ollama API error: ' . $message);
        }

        return $data;
    }
}

