<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\AI\Export\ExportOptions;
use FlowEngine\AI\LLM\LLMProvider;
use FlowEngine\AI\LLM\LLMRequest;
use FlowEngine\AI\Prompt\SystemPrompt;
use FlowEngine\Application\DTO\QuestionResponseDTO;

final class AskQuestion
{
    public function __construct(
        private LLMProvider $llmProvider,
        private ExportContext $exportContext
    ) {
    }

    public function execute(string $question, ?ExportOptions $options = null): QuestionResponseDTO
    {
        $export = $this->exportContext->execute($options);

        $request = new LLMRequest(
            systemPrompt: SystemPrompt::text(),
            userPrompt: $question,
            context: [$export->markdown],
            maxTokens: 4096
        );

        $response = $this->llmProvider->send($request);

        return new QuestionResponseDTO(
            question: $question,
            answer: $response->content,
            tokensUsed: $response->tokensUsed,
            metadata: array_merge($response->metadata, [
                'contextTokenEstimate' => $export->tokenEstimate,
                'includedSections' => $export->includedSections,
                'providerConfigured' => $this->llmProvider->isConfigured(),
            ])
        );
    }
}
