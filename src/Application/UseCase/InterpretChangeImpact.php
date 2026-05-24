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

final class InterpretChangeImpact
{
    public function __construct(
        private AssessNodeImpact $assessNodeImpact,
        private LLMProvider $llmProvider,
        private ContextAssembler $assembler,
        private PromptBuilder $promptBuilder,
        private Flow $flow
    ) {
    }

    public function execute(string $nodeId): InterpretationResultDTO
    {
        $report = $this->assessNodeImpact->execute($nodeId);

        if (count($report->upstream) === 0 && count($report->downstream) === 0) {
            return new InterpretationResultDTO(
                type: 'changeImpact',
                interpretation: "Node {$nodeId} has no dependencies. Change risk is minimal.",
                tokensUsed: 0,
                context: $report->toArray()
            );
        }

        $context = $this->assembler->changeImpact($report);
        $template = InterpretationPrompts::changeImpact();
        $contextArray = [
            'nodeId' => $context->nodeId,
            'upstream' => $context->upstream,
            'downstream' => $context->downstream,
            'blastRadius' => $context->blastRadius,
            'fanIn' => $context->fanIn,
            'fanOut' => $context->fanOut,
            'riskLevel' => $context->riskLevel,
            'complexityScore' => $context->complexityScore,
            'cyclesInvolved' => $context->cyclesInvolved,
            'violationsInvolved' => $context->violationsInvolved,
            'riskSummary' => $context->riskSummary,
        ];

        $userPrompt = $this->promptBuilder->buildUserPrompt($template, $contextArray);

        $response = $this->llmProvider->send(new LLMRequest(
            systemPrompt: SystemPrompt::text(),
            userPrompt: $userPrompt,
            maxTokens: 4096
        ));

        $grounding = (new GraphGroundingValidator($this->flow))->validate($response->content);

        return new InterpretationResultDTO(
            type: 'changeImpact',
            interpretation: $response->content,
            tokensUsed: $response->tokensUsed,
            context: $contextArray,
            metadata: array_merge($response->metadata, [
                'grounding' => $grounding,
            ])
        );
    }
}
