<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\AI\Context\ContextAssembler;
use FlowEngine\AI\LLM\LLMProvider;
use FlowEngine\AI\LLM\LLMRequest;
use FlowEngine\AI\Prompt\InterpretationPrompts;
use FlowEngine\AI\Prompt\PromptBuilder;
use FlowEngine\AI\Prompt\SystemPrompt;
use FlowEngine\Application\DTO\RefactorGuidanceDTO;
use FlowEngine\Domain\Contracts\SnapshotStorePort;

final class GetRefactorGuidance
{
    public function __construct(
        private AssessNodeImpact $assessNodeImpact,
        private LLMProvider $llmProvider,
        private ContextAssembler $assembler,
        private PromptBuilder $promptBuilder,
        private SnapshotStorePort $snapshotStore
    ) {
    }

    public function execute(string $planLabel, int $stepNumber): RefactorGuidanceDTO
    {
        // 1. Load saved plan
        $payload = $this->snapshotStore->load($planLabel);

        if (($payload['type'] ?? '') !== 'refactor_plan') {
            throw new \RuntimeException("Snapshot '{$planLabel}' is not a refactor plan.");
        }

        $planData = $payload['plan'] ?? [];
        $nodeId = $planData['nodeId'] ?? '';
        $steps = $planData['steps'] ?? [];

        // 2. Find the requested step
        $step = $this->findStep($steps, $stepNumber);

        if ($step === null) {
            throw new \RuntimeException("Step {$stepNumber} not found in plan '{$planLabel}'.");
        }

        // 3. Re-analyse the node to get current metrics
        $impact = $this->assessNodeImpact->execute($nodeId);

        // 4. Fallback: no LLM configured
        if (!$this->llmProvider->isConfigured()) {
            return $this->buildFallbackGuidance($nodeId, $step, $impact->fanIn, $impact->fanOut, $impact->blastRadius);
        }

        // 5. Build context for the prompt
        $contextArray = [
            'nodeId' => $nodeId,
            'stepOrder' => $step['order'],
            'stepAction' => $step['action'],
            'stepTarget' => $step['target'],
            'stepRationale' => $step['rationale'],
            'stepPriority' => $step['priority'],
            'stepAffectedNodes' => $step['affectedNodes'] ?? [],
            'currentFanIn' => $impact->fanIn,
            'currentFanOut' => $impact->fanOut,
            'currentBlastRadius' => $impact->blastRadius,
            'currentRiskLevel' => $impact->riskLevel,
            'currentCyclesInvolved' => count($impact->cyclesInvolved),
            'currentViolationsInvolved' => count($impact->violationsInvolved),
        ];

        $template = InterpretationPrompts::refactorGuidance();
        $userPrompt = $this->promptBuilder->buildUserPrompt($template, $contextArray);

        // 6. Call LLM (with graceful fallback on API errors)
        try {
            $response = $this->llmProvider->send(new LLMRequest(
                systemPrompt: SystemPrompt::text(),
                userPrompt: $userPrompt,
                maxTokens: 4096
            ));
        } catch (\FlowEngine\AI\LLM\LLMException $e) {
            return $this->buildFallbackGuidance($nodeId, $step, $impact->fanIn, $impact->fanOut, $impact->blastRadius);
        }

        // 7. Parse response
        $guidanceData = $this->parseAIResponse($response->content);

        return new RefactorGuidanceDTO(
            nodeId: $nodeId,
            stepOrder: $step['order'],
            stepAction: $step['action'],
            stepTarget: $step['target'],
            actionableInstructions: $guidanceData['actionableInstructions'] ?? [],
            codePatterns: $guidanceData['codePatterns'] ?? [],
            warningsToAvoid: $guidanceData['warningsToAvoid'] ?? [],
            verificationChecklist: $guidanceData['verificationChecklist'] ?? [],
            estimatedEffort: $guidanceData['estimatedEffort'] ?? 'Unknown',
            metadata: [
                'tokensUsed' => $response->tokensUsed,
                'llmConfigured' => true,
            ]
        );
    }

    private function findStep(array $steps, int $stepNumber): ?array
    {
        foreach ($steps as $step) {
            if (($step['order'] ?? null) === $stepNumber) {
                return $step;
            }
        }

        return null;
    }

    private function buildFallbackGuidance(
        string $nodeId,
        array $step,
        int $fanIn,
        int $fanOut,
        int $blastRadius
    ): RefactorGuidanceDTO {
        $rationale = $step['rationale'] ?? '';
        $action = $step['action'] ?? '';
        $target = $step['target'] ?? $nodeId;

        return new RefactorGuidanceDTO(
            nodeId: $nodeId,
            stepOrder: $step['order'] ?? 0,
            stepAction: $action,
            stepTarget: $target,
            actionableInstructions: [
                "Apply the following change to `{$target}`: {$action}.",
                "Rationale: {$rationale}",
                'Run the test suite after each atomic change.',
            ],
            codePatterns: [],
            warningsToAvoid: [
                'Do not change multiple concerns in a single commit.',
                'Ensure all callers are updated if the signature changes.',
            ],
            verificationChecklist: [
                'All existing tests pass.',
                'No new architecture violations introduced.',
                "Fan-in ({$fanIn}), Fan-out ({$fanOut}), Blast radius ({$blastRadius}) have not worsened.",
            ],
            estimatedEffort: 'Unknown (LLM not configured)',
            metadata: [
                'tokensUsed' => 0,
                'llmConfigured' => false,
            ]
        );
    }

    private function parseAIResponse(string $response): array
    {
        $json = $response;
        if (preg_match('/```(?:json)?\s*\n(.*?)\n```/s', $response, $matches)) {
            $json = $matches[1];
        }

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            throw new \RuntimeException('AI response is not a valid JSON object');
        }

        return $data;
    }
}
