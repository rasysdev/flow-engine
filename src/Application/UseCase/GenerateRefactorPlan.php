<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\AI\Context\ContextAssembler;
use FlowEngine\AI\LLM\LLMProvider;
use FlowEngine\AI\LLM\LLMRequest;
use FlowEngine\AI\Prompt\InterpretationPrompts;
use FlowEngine\AI\Prompt\PromptBuilder;
use FlowEngine\AI\Prompt\SystemPrompt;
use FlowEngine\AI\Validation\GraphGroundingValidator;
use FlowEngine\Application\DTO\RefactorPlanDTO;
use FlowEngine\Application\DTO\RefactorPrerequisiteDTO;
use FlowEngine\Application\DTO\RefactorStepDTO;
use FlowEngine\Domain\Contracts\Flow;

final class GenerateRefactorPlan
{
    public function __construct(
        private AssessNodeImpact $assessNodeImpact,
        private AssessRefactorSafety $assessRefactorSafety,
        private ScoreChangeRisk $scoreChangeRisk,
        private LLMProvider $llmProvider,
        private ContextAssembler $assembler,
        private PromptBuilder $promptBuilder,
        private Flow $flow
    ) {
    }

    public function execute(string $nodeId): RefactorPlanDTO
    {
        // 1. Gather analysis from existing v2.x use cases
        $impact = $this->assessNodeImpact->execute($nodeId);
        $safety = $this->assessRefactorSafety->execute($nodeId);
        $risk = $this->scoreChangeRisk->execute($nodeId);

        // 2. Early exit for trivial nodes (saves LLM costs)
        if ($this->isTrivialNode($impact->riskLevel, $safety->affectedNodes, $impact->cyclesInvolved, $impact->violationsInvolved)) {
            return $this->createTrivialPlan($nodeId, $risk->score, $risk->level, $risk->factors);
        }

        // 3a. Early exit if LLM is not configured
        if (!$this->llmProvider->isConfigured()) {
            return $this->createNoLLMPlan($nodeId, $risk->score, $risk->level, $risk->factors, $safety);
        }

        // 3. Build AI context
        $context = $this->assembler->refactorPlan($impact, $safety, $risk);

        // 4. Build prompt with structured data
        $template = InterpretationPrompts::refactorPlan();
        $contextArray = [
            'nodeId' => $context->nodeId,
            'upstream' => $context->upstream,
            'downstream' => $context->downstream,
            'blastRadius' => $context->blastRadius,
            'fanIn' => $context->fanIn,
            'fanOut' => $context->fanOut,
            'complexityScore' => $context->complexityScore,
            'riskLevel' => $context->riskLevel,
            'riskScore' => $context->riskScore,
            'riskFactors' => $context->riskFactors,
            'cyclesInvolved' => $context->cyclesInvolved,
            'violationsInvolved' => $context->violationsInvolved,
            'cyclesAffected' => $context->cyclesAffected,
            'violationsAffected' => $context->violationsAffected,
            'potentialOrphans' => $context->potentialOrphans,
            'affectedNodeCount' => $context->affectedNodeCount,
        ];

        $userPrompt = $this->promptBuilder->buildUserPrompt($template, $contextArray);

        // 5. Call LLM (with graceful fallback on API errors)
        try {
            $response = $this->llmProvider->send(new LLMRequest(
                systemPrompt: SystemPrompt::text(),
                userPrompt: $userPrompt,
                maxTokens: 8192
            ));
        } catch (\FlowEngine\AI\LLM\LLMException $e) {
            return $this->createNoLLMPlan($nodeId, $risk->score, $risk->level, $risk->factors, $safety);
        }

        // 6. Parse JSON response
        $planData = $this->parseAIResponse($response->content);

        // 7. Validate grounding
        $grounding = (new GraphGroundingValidator($this->flow))->validate($response->content);

        // 8. Build DTOs from parsed data
        $prerequisites = array_map(
            fn(array $p) => new RefactorPrerequisiteDTO(
                type: $p['type'] ?? 'unknown',
                description: $p['description'] ?? '',
                affectedNodes: $p['affectedNodes'] ?? [],
                severity: $p['severity'] ?? 'MEDIUM',
                recommendation: $p['recommendation'] ?? ''
            ),
            $planData['prerequisites'] ?? []
        );

        $steps = array_map(
            fn(array $s) => new RefactorStepDTO(
                order: $s['order'] ?? 0,
                action: $s['action'] ?? '',
                target: $s['target'] ?? $nodeId,
                rationale: $s['rationale'] ?? '',
                priority: $s['priority'] ?? 'MEDIUM',
                affectedNodes: $s['affectedNodes'] ?? [],
                tests: $s['tests'] ?? []
            ),
            $planData['steps'] ?? []
        );

        return new RefactorPlanDTO(
            nodeId: $nodeId,
            detectionReason: $planData['detectionReason'] ?? 'Unknown',
            overallRisk: $risk->level,
            riskScore: $risk->score,
            riskFactors: $risk->factors,
            prerequisites: $prerequisites,
            steps: $steps,
            testingGuidance: $planData['testingGuidance'] ?? [],
            estimatedComplexity: $planData['estimatedComplexity'] ?? 5,
            metadata: [
                'tokensUsed' => $response->tokensUsed,
                'grounding' => $grounding,
                'trivial' => false,
            ]
        );
    }

    private function createNoLLMPlan(string $nodeId, int $riskScore, string $riskLevel, array $riskFactors, \FlowEngine\Application\DTO\SafetyAssessmentDTO $safety): RefactorPlanDTO
    {
        return new RefactorPlanDTO(
            nodeId: $nodeId,
            detectionReason: 'LLM provider is not configured. Configure ANTHROPIC_API_KEY, OPENAI_API_KEY, or OLLAMA_HOST + OLLAMA_MODEL to get AI-generated plans.',
            overallRisk: $riskLevel,
            riskScore: $riskScore,
            riskFactors: $riskFactors,
            prerequisites: [],
            steps: [],
            testingGuidance: array_map(fn(string $r) => $r, $safety->recommendations),
            estimatedComplexity: 0,
            metadata: [
                'tokensUsed' => 0,
                'grounding' => ['valid' => true, 'invalidReferences' => []],
                'trivial' => false,
                'llmConfigured' => false,
            ]
        );
    }

    private function isTrivialNode(string $riskLevel, int $affectedNodes, array $cycles, array $violations): bool
    {
        // Trivial if: LOW risk, < 3 affected nodes, no cycles, no violations
        return $riskLevel === 'LOW' && $affectedNodes < 3 && empty($cycles) && empty($violations);
    }

    private function createTrivialPlan(string $nodeId, int $riskScore, string $riskLevel, array $riskFactors): RefactorPlanDTO
    {
        return new RefactorPlanDTO(
            nodeId: $nodeId,
            detectionReason: 'This node has minimal complexity and low coupling. Refactoring is straightforward.',
            overallRisk: $riskLevel,
            riskScore: $riskScore,
            riskFactors: $riskFactors,
            prerequisites: [],
            steps: [],
            testingGuidance: ['Add or verify unit tests before making changes.'],
            estimatedComplexity: 1,
            metadata: [
                'tokensUsed' => 0,
                'grounding' => ['valid' => true, 'invalidReferences' => []],
                'trivial' => true,
            ]
        );
    }

    /**
     * @throws \RuntimeException if JSON parsing fails
     */
    private function parseAIResponse(string $response): array
    {
        // Strip markdown code blocks if present
        $json = $response;
        if (preg_match('/```(?:json)?\s*\n(.*?)\n```/s', $response, $matches)) {
            $json = $matches[1];
        }

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            throw new \RuntimeException('AI response is not a valid JSON object');
        }

        // Validate required fields
        if (!isset($data['detectionReason'])) {
            throw new \RuntimeException('AI response missing required field: detectionReason');
        }

        return $data;
    }
}
