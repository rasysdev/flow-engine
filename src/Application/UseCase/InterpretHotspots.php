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

final class InterpretHotspots
{
    public function __construct(
        private AnalyzeComplexity $analyzeComplexity,
        private LLMProvider $llmProvider,
        private ContextAssembler $assembler,
        private PromptBuilder $promptBuilder,
        private Flow $flow
    ) {
    }

    public function execute(): InterpretationResultDTO
    {
        $report = $this->analyzeComplexity->execute();

        if (count($report->complexMethods) === 0) {
            return new InterpretationResultDTO(
                type: 'hotspots',
                interpretation: 'No complexity hotspots detected.',
                tokensUsed: 0,
                context: $report->toArray()
            );
        }

        $context = $this->assembler->hotspots($report);
        $template = InterpretationPrompts::hotspots();
        $contextArray = [
            'totalMethods' => $context->totalMethods,
            'avgComplexity' => $context->avgComplexity,
            'maxComplexity' => $context->maxComplexity,
            'byLevel' => $context->byLevel,
            'complexMethods' => $context->complexMethods,
        ];

        $userPrompt = $this->promptBuilder->buildUserPrompt($template, $contextArray);

        $response = $this->llmProvider->send(new LLMRequest(
            systemPrompt: SystemPrompt::text(),
            userPrompt: $userPrompt,
            maxTokens: 4096
        ));

        $grounding = (new GraphGroundingValidator($this->flow))->validate($response->content);

        return new InterpretationResultDTO(
            type: 'hotspots',
            interpretation: $response->content,
            tokensUsed: $response->tokensUsed,
            context: $contextArray,
            metadata: array_merge($response->metadata, [
                'grounding' => $grounding,
            ])
        );
    }
}
