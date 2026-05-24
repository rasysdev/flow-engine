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

final class InterpretCycles
{
    public function __construct(
        private AnalyzeCycles $analyzeCycles,
        private LLMProvider $llmProvider,
        private ContextAssembler $assembler,
        private PromptBuilder $promptBuilder,
        private Flow $flow
    ) {
    }

    public function execute(): InterpretationResultDTO
    {
        $report = $this->analyzeCycles->execute();

        if ($report->totalCycles === 0) {
            return new InterpretationResultDTO(
                type: 'cycles',
                interpretation: 'No dependency cycles detected.',
                tokensUsed: 0,
                context: $report->toArray()
            );
        }

        $context = $this->assembler->cycles($report);
        $template = InterpretationPrompts::cycles();
        $contextArray = [
            'totalCycles' => $context->totalCycles,
            'totalNodesInCycles' => $context->totalNodesInCycles,
            'bySeverity' => $context->bySeverity,
            'largestCycle' => $context->largestCycle,
            'cycles' => $context->cycles,
        ];

        $userPrompt = $this->promptBuilder->buildUserPrompt($template, $contextArray);

        $response = $this->llmProvider->send(new LLMRequest(
            systemPrompt: SystemPrompt::text(),
            userPrompt: $userPrompt,
            maxTokens: 4096
        ));

        $grounding = (new GraphGroundingValidator($this->flow))->validate($response->content);

        return new InterpretationResultDTO(
            type: 'cycles',
            interpretation: $response->content,
            tokensUsed: $response->tokensUsed,
            context: $contextArray,
            metadata: array_merge($response->metadata, [
                'grounding' => $grounding,
            ])
        );
    }
}
