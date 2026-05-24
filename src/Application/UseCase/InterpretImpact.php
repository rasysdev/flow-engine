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

final class InterpretImpact
{
    public function __construct(
        private AnalyzeImpact $analyzeImpact,
        private LLMProvider $llmProvider,
        private ContextAssembler $assembler,
        private PromptBuilder $promptBuilder,
        private Flow $flow
    ) {
    }

    public function execute(string $nodeId): InterpretationResultDTO
    {
        $impactResult = $this->analyzeImpact->execute($nodeId);

        $upstream = $impactResult['impact']['upstream'] ?? [];
        $downstream = $impactResult['impact']['downstream'] ?? [];

        if (count($upstream) === 0 && count($downstream) === 0) {
            return new InterpretationResultDTO(
                type: 'impact',
                interpretation: "Node {$nodeId} has no upstream or downstream dependencies.",
                tokensUsed: 0,
                context: $impactResult
            );
        }

        $context = $this->assembler->trace($nodeId, $impactResult);
        $template = InterpretationPrompts::impact();
        $contextArray = [
            'nodeId' => $context->nodeId,
            'upstream' => $context->upstream,
            'downstream' => $context->downstream,
        ];

        $userPrompt = $this->promptBuilder->buildUserPrompt($template, $contextArray);

        $response = $this->llmProvider->send(new LLMRequest(
            systemPrompt: SystemPrompt::text(),
            userPrompt: $userPrompt,
            maxTokens: 4096
        ));

        $grounding = (new GraphGroundingValidator($this->flow))->validate($response->content);

        return new InterpretationResultDTO(
            type: 'impact',
            interpretation: $response->content,
            tokensUsed: $response->tokensUsed,
            context: $contextArray,
            metadata: array_merge($response->metadata, [
                'grounding' => $grounding,
            ])
        );
    }
}
