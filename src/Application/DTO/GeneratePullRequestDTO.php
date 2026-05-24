<?php

namespace FlowEngine\Application\DTO;

/**
 * @api
 */
final readonly class GeneratePullRequestDTO
{
    /**
     * @param string   $title              PR title (e.g. "refactor: reduce fan-out in MyClass::execute [HIGH, 3 steps]")
     * @param string   $body               Full PR body in Markdown
     * @param string   $branch             Suggested git branch name (e.g. "refactor/myclass-execute")
     * @param string   $nodeId             Target node (e.g. "App\Service\MyClass::execute")
     * @param string   $riskLevel          LOW|MEDIUM|HIGH|CRITICAL
     * @param int      $riskScore          0–100
     * @param int      $stepsCount         Number of refactoring steps in the plan
     * @param int      $prerequisitesCount Number of blocking prerequisites
     * @param string[] $affectedNodes      All node IDs affected across all steps (deduped)
     * @param string[] $testingGuidance    Test recommendations from the plan
     * @param string   $planLabel          Snapshot label the plan was loaded from
     */
    public function __construct(
        public string $title,
        public string $body,
        public string $branch,
        public string $nodeId,
        public string $riskLevel,
        public int    $riskScore,
        public int    $stepsCount,
        public int    $prerequisitesCount,
        public array  $affectedNodes,
        public array  $testingGuidance,
        public string $planLabel,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'title'              => $this->title,
            'body'               => $this->body,
            'branch'             => $this->branch,
            'nodeId'             => $this->nodeId,
            'riskLevel'          => $this->riskLevel,
            'riskScore'          => $this->riskScore,
            'stepsCount'         => $this->stepsCount,
            'prerequisitesCount' => $this->prerequisitesCount,
            'affectedNodes'      => $this->affectedNodes,
            'testingGuidance'    => $this->testingGuidance,
            'planLabel'          => $this->planLabel,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
