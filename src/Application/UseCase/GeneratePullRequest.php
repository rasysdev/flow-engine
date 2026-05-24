<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\AI\LLM\LLMProvider;
use FlowEngine\AI\LLM\LLMRequest;
use FlowEngine\AI\Prompt\InterpretationPrompts;
use FlowEngine\AI\Prompt\PromptBuilder;
use FlowEngine\AI\Prompt\SystemPrompt;
use FlowEngine\Application\DTO\GeneratePullRequestDTO;
use FlowEngine\Domain\Contracts\SnapshotStorePort;
use RuntimeException;

/**
 * Generates a pull-request title, body, and branch suggestion from a saved refactor plan.
 *
 * Always produces a deterministic, structured PR body. When an LLM is configured,
 * optionally prepends a short AI-written introduction paragraph.
 *
 * Publishing to GitHub (via `gh pr create`) is handled in the CLI layer — this use case
 * only produces the DTO and never auto-pushes.
 */
final class GeneratePullRequest
{
    public function __construct(
        private SnapshotStorePort $snapshotStore,
        private LLMProvider   $llmProvider,
        private PromptBuilder $promptBuilder,
    ) {}

    public function execute(string $planLabel): GeneratePullRequestDTO
    {
        $data = $this->snapshotStore->load($planLabel);

        if (($data['type'] ?? '') !== 'refactor_plan') {
            throw new RuntimeException("Snapshot '{$planLabel}' is not a refactor plan (type: " . ($data['type'] ?? 'unknown') . ")");
        }

        $planData = $data['plan'];

        $nodeId          = $planData['nodeId']          ?? '';
        $riskLevel       = $planData['overallRisk']     ?? 'UNKNOWN';
        $riskScore       = (int) ($planData['riskScore'] ?? 0);
        $steps           = $planData['steps']           ?? [];
        $prerequisites   = $planData['prerequisites']   ?? [];
        $testingGuidance = $planData['testingGuidance'] ?? [];
        $detectionReason = $planData['detectionReason'] ?? '';

        // Collect and deduplicate affected nodes across all steps
        $affectedNodes = [];
        foreach ($steps as $step) {
            foreach ($step['affectedNodes'] ?? [] as $node) {
                $affectedNodes[$node] = true;
            }
        }

        $branch = $this->branchName($nodeId);
        $title  = $this->prTitle($nodeId, $riskLevel, count($steps));

        // Optional LLM intro paragraph (silently skipped when LLM is not configured)
        $aiIntro = '';
        if ($this->llmProvider->isConfigured()) {
            $aiIntro = $this->generateIntro($nodeId, $detectionReason, $riskLevel, count($prerequisites), count($steps));
        }

        $body = $this->buildBody($planData, $aiIntro);

        return new GeneratePullRequestDTO(
            title:              $title,
            body:               $body,
            branch:             $branch,
            nodeId:             $nodeId,
            riskLevel:          $riskLevel,
            riskScore:          $riskScore,
            stepsCount:         count($steps),
            prerequisitesCount: count($prerequisites),
            affectedNodes:      array_keys($affectedNodes),
            testingGuidance:    $testingGuidance,
            planLabel:          $planLabel,
        );
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function branchName(string $nodeId): string
    {
        $slug = strtolower(str_replace(['\\', '::', ' '], '-', $nodeId));
        $slug = preg_replace('/[^a-z0-9-]/', '', $slug) ?? $slug;
        $slug = preg_replace('/-+/', '-', $slug)         ?? $slug;
        return 'refactor/' . trim($slug, '-');
    }

    private function prTitle(string $nodeId, string $riskLevel, int $stepsCount): string
    {
        // Keep only the last segment (Class::method) to keep titles concise
        $parts = explode('\\', $nodeId);
        $short = end($parts);
        $steps = $stepsCount === 1 ? '1 step' : "{$stepsCount} steps";
        return "refactor: {$short} [{$riskLevel}, {$steps}]";
    }

    private function generateIntro(
        string $nodeId,
        string $detectionReason,
        string $riskLevel,
        int    $prerequisitesCount,
        int    $stepsCount
    ): string {
        try {
            $prompt     = InterpretationPrompts::pullRequest();
            $userPrompt = $this->promptBuilder->buildUserPrompt($prompt, [
                'nodeId'              => $nodeId,
                'detectionReason'     => $detectionReason,
                'riskLevel'           => $riskLevel,
                'prerequisitesCount'  => $prerequisitesCount,
                'stepsCount'          => $stepsCount,
            ]);
            $response = $this->llmProvider->send(new LLMRequest(
                systemPrompt: SystemPrompt::text(),
                userPrompt:   $userPrompt,
                maxTokens:    256,
            ));
            $intro = trim($response->content);
            return $intro !== '' ? $intro : '';
        } catch (\Throwable) {
            return '';
        }
    }

    private function buildBody(array $planData, string $aiIntro): string
    {
        $nodeId          = $planData['nodeId']          ?? '';
        $riskLevel       = $planData['overallRisk']     ?? 'UNKNOWN';
        $riskScore       = (int) ($planData['riskScore'] ?? 0);
        $steps           = $planData['steps']           ?? [];
        $prerequisites   = $planData['prerequisites']   ?? [];
        $testingGuidance = $planData['testingGuidance'] ?? [];
        $detectionReason = $planData['detectionReason'] ?? '';
        $complexity      = $planData['estimatedComplexity'] ?? '—';

        $md = '';

        if ($aiIntro !== '') {
            $md .= $aiIntro . "\n\n";
        }

        $md .= "## Why this change?\n\n";
        $md .= "{$detectionReason}\n\n";

        $md .= "## Risk summary\n\n";
        $md .= "| Metric | Value |\n|---|---|\n";
        $md .= "| Risk level | {$riskLevel} |\n";
        $md .= "| Risk score | {$riskScore} / 100 |\n";
        $md .= "| Steps | " . count($steps) . " |\n";
        $md .= "| Prerequisites | " . count($prerequisites) . " |\n";
        $md .= "| Estimated complexity | {$complexity} / 10 |\n\n";

        if ($prerequisites !== []) {
            $md .= "## Prerequisites\n\n";
            $md .= "Resolve these before merging:\n\n";
            foreach ($prerequisites as $p) {
                $severity = $p['severity'] ?? 'UNKNOWN';
                $type     = $p['type']     ?? 'unknown';
                $desc     = $p['description'] ?? '';
                $rec      = $p['recommendation'] ?? '';
                $md .= "- **[{$severity}] {$type}:** {$desc}";
                if ($rec !== '') {
                    $md .= " → {$rec}";
                }
                $md .= "\n";
            }
            $md .= "\n";
        }

        if ($steps !== []) {
            $md .= "## Refactoring checklist\n\n";
            foreach ($steps as $step) {
                $order  = $step['order']  ?? '?';
                $action = $step['action'] ?? '';
                $target = $step['target'] ?? '';
                $md .= "- [ ] **Step {$order}:** {$action}";
                if ($target !== '') {
                    $md .= " — `{$target}`";
                }
                $md .= "\n";
            }
            $md .= "\n";
        }

        if ($testingGuidance !== []) {
            $md .= "## Testing\n\n";
            foreach ($testingGuidance as $guidance) {
                $md .= "- {$guidance}\n";
            }
            $md .= "\n";
        }

        $md .= "---\n\n";
        $md .= "*Generated by [Flow Engine](https://github.com/rborges/flow-engine) from plan `{$nodeId}`*\n";

        return $md;
    }
}
