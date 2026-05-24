<?php

namespace FlowEngine\Infrastructure\LLM;

use FlowEngine\AI\LLM\LLMException;
use FlowEngine\AI\LLM\LLMProvider;
use FlowEngine\AI\LLM\LLMRequest;
use FlowEngine\AI\LLM\LLMResponse;

final class AnthropicProvider implements LLMProvider
{
    private const PROVIDER = 'anthropic';

    public function __construct(
        private AnthropicConfig $config
    ) {
    }

    public function send(LLMRequest $request): LLMResponse
    {
        $payload = $this->buildPayload($request);
        $responseData = $this->executeRequest($payload);

        return $this->parseResponse($responseData);
    }

    public function isConfigured(): bool
    {
        return $this->config->apiKey !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPayload(LLMRequest $request): array
    {
        $userContent = '';

        if ($request->context !== []) {
            $userContent .= implode("\n\n", $request->context) . "\n\n";
        }

        $userContent .= $request->userPrompt;

        return [
            'model' => $this->config->model,
            'max_tokens' => $request->maxTokens,
            'system' => $request->systemPrompt,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $userContent,
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $responseData
     */
    public static function parseResponse(array $responseData): LLMResponse
    {
        if (isset($responseData['error'])) {
            throw new LLMException(
                'Anthropic API error: ' . ($responseData['error']['message'] ?? 'Unknown error'),
                'LLM_PROVIDER_UNAVAILABLE',
                ['Verifique status da API Anthropic e tente novamente.'],
                true,
                self::PROVIDER
            );
        }

        $content = '';
        foreach ($responseData['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $content .= $block['text'];
            }
        }

        $tokensUsed = ($responseData['usage']['input_tokens'] ?? 0)
            + ($responseData['usage']['output_tokens'] ?? 0);

        return new LLMResponse(
            content: $content,
            tokensUsed: $tokensUsed,
            metadata: [
                'provider' => 'anthropic',
                'model' => $responseData['model'] ?? '',
                'stop_reason' => $responseData['stop_reason'] ?? '',
                'input_tokens' => $responseData['usage']['input_tokens'] ?? 0,
                'output_tokens' => $responseData['usage']['output_tokens'] ?? 0,
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
            throw new LLMException(
                'Failed to initialize cURL',
                'LLM_UNKNOWN',
                ['Verifique a instalacao do cURL no ambiente PHP.'],
                false,
                self::PROVIDER
            );
        }

        $curlOptions = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->config->apiKey,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_TIMEOUT => 120,
        ];

        // SSL: prefer explicit CA bundle from env vars (CURL_CA_BUNDLE, SSL_CERT_FILE),
        // then fall back to php.ini curl.cainfo; if none exist, honour
        // FLOW_ENGINE_LLM_NO_SSL_VERIFY=1 for development environments only.
        $caBundle = getenv('CURL_CA_BUNDLE') ?: getenv('SSL_CERT_FILE') ?: (ini_get('curl.cainfo') ?: '');
        if ($caBundle !== '' && file_exists($caBundle)) {
            $curlOptions[CURLOPT_CAINFO] = $caBundle;
        } elseif (getenv('FLOW_ENGINE_LLM_NO_SSL_VERIFY') === '1') {
            $curlOptions[CURLOPT_SSL_VERIFYPEER] = false;
            $curlOptions[CURLOPT_SSL_VERIFYHOST] = 0;
        }

        curl_setopt_array($ch, $curlOptions);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw $this->mapCurlError($error);
        }

        $data = json_decode($response, true);

        if (!is_array($data)) {
            throw new LLMException(
                'Invalid JSON response from Anthropic API',
                'LLM_PROVIDER_UNAVAILABLE',
                ['A API retornou resposta invalida. Tente novamente em instantes.'],
                true,
                self::PROVIDER
            );
        }

        if ($httpCode !== 200) {
            $message = $data['error']['message'] ?? "HTTP {$httpCode}";
            if ($httpCode === 401 || $httpCode === 403) {
                throw new LLMException(
                    'Anthropic API error: ' . $message,
                    'LLM_AUTH_INVALID_KEY',
                    ['Valide a ANTHROPIC_API_KEY configurada para o ambiente atual.'],
                    false,
                    self::PROVIDER
                );
            }
            throw new LLMException(
                'Anthropic API error: ' . $message,
                'LLM_PROVIDER_UNAVAILABLE',
                ['A API Anthropic nao respondeu com sucesso. Tente novamente.'],
                true,
                self::PROVIDER
            );
        }

        return $data;
    }

    private function mapCurlError(string $error): LLMException
    {
        $normalized = strtolower($error);
        if (
            str_contains($normalized, 'certificate') ||
            str_contains($normalized, 'ssl') ||
            str_contains($normalized, 'issuer')
        ) {
            return new LLMException(
                'Falha de SSL ao conectar no provedor LLM. Cadeia de certificado nao confiavel.',
                'LLM_SSL_CERT_INVALID',
                [
                    'Configure CURL_CA_BUNDLE ou SSL_CERT_FILE com o caminho do CA bundle.',
                    'Para desenvolvimento local apenas, use FLOW_ENGINE_LLM_NO_SSL_VERIFY=1.',
                ],
                false,
                self::PROVIDER
            );
        }
        if (
            str_contains($normalized, 'timed out') ||
            str_contains($normalized, 'could not resolve host') ||
            str_contains($normalized, 'failed to connect')
        ) {
            return new LLMException(
                'Falha de rede ao conectar no provedor LLM.',
                'LLM_NETWORK',
                ['Verifique conectividade e DNS do ambiente.'],
                true,
                self::PROVIDER
            );
        }

        return new LLMException(
            'cURL error: ' . $error,
            'LLM_UNKNOWN',
            ['Revise configuracao de rede e credenciais do provedor.'],
            false,
            self::PROVIDER
        );
    }
}
