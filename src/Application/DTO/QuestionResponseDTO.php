<?php

namespace FlowEngine\Application\DTO;

final readonly class QuestionResponseDTO
{
    /**
     * @param string $question
     * @param string $answer
     * @param int $tokensUsed
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $question,
        public string $answer,
        public int $tokensUsed,
        public array $metadata = []
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'question' => $this->question,
            'answer' => $this->answer,
            'tokensUsed' => $this->tokensUsed,
            'metadata' => $this->metadata,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }
}
