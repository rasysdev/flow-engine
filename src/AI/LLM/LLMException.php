<?php

namespace FlowEngine\AI\LLM;

class LLMException extends \RuntimeException
{
    /**
     * @param array<int, string> $hints
     */
    public function __construct(
        string $message,
        private readonly string $errorCode = 'LLM_UNKNOWN',
        private readonly array $hints = [],
        private readonly bool $retryable = false,
        private readonly ?string $provider = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * @return array<int, string>
     */
    public function hints(): array
    {
        return $this->hints;
    }

    public function retryable(): bool
    {
        return $this->retryable;
    }

    public function provider(): ?string
    {
        return $this->provider;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'error' => $this->getMessage(),
            'errorCode' => $this->errorCode,
            'hints' => $this->hints,
            'retryable' => $this->retryable,
            'provider' => $this->provider,
        ];
    }
}
