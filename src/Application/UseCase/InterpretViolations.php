<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\AI\Context\ContextAssembler;
use FlowEngine\AI\LLM\LLMProvider;
use FlowEngine\AI\LLM\LLMRequest;
use FlowEngine\AI\Prompt\InterpretationPrompts;
use FlowEngine\AI\Prompt\PromptBuilder;
use FlowEngine\AI\Prompt\SystemPrompt;
use FlowEngine\AI\Validation\GraphGroundingValidator;
use FlowEngine\Application\DTO\InterpretationResultDTO;
use FlowEngine\Domain\Contracts\Flow;

final class InterpretViolations
{
    public function __construct(
        private AnalyzeArchitecture $analyzeArchitecture,
        private LLMProvider $llmProvider,
        private ContextAssembler $assembler,
        private PromptBuilder $promptBuilder,
        private Flow $flow
    ) {
    }

    public function execute(): InterpretationResultDTO
    {
        $report = $this->analyzeArchitecture->execute();

        if ($report->isClean) {
            return new InterpretationResultDTO(
                type: 'violations',
                interpretation: 'No architecture violations detected. The codebase follows clean architecture principles.',
                tokensUsed: 0,
                context: $report->toArray()
            );
        }

        $context = $this->assembler->violations($report);
        $template = InterpretationPrompts::violations();
        $contextArray = [
            'isClean' => $context->isClean,
            'totalViolations' => $context->totalViolations,
            'bySeverity' => $context->bySeverity,
            'byType' => $context->byType,
            'layerDistribution' => $context->layerDistribution,
            'violations' => $context->violations,
        ];

        $userPrompt = $this->promptBuilder->buildUserPrompt($template, $contextArray);

        $response = $this->llmProvider->send(new LLMRequest(
            systemPrompt: SystemPrompt::text(),
            userPrompt: $userPrompt,
            maxTokens: 4096
        ));

        $grounding = (new GraphGroundingValidator($this->flow))->validate($response->content);

        return new InterpretationResultDTO(
            type: 'violations',
            interpretation: $response->content,
            tokensUsed: $response->tokensUsed,
            context: $contextArray,
            metadata: array_merge($response->metadata, [
                'grounding' => $grounding,
            ])
        );
    }
}
