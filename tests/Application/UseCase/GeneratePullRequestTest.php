<?php

namespace Tests\Application\UseCase;

use FlowEngine\AI\LLM\NullLLMProvider;
use FlowEngine\AI\Prompt\PromptBuilder;
use FlowEngine\Application\UseCase\GeneratePullRequest;
use FlowEngine\Infrastructure\Cache\SnapshotStore;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestProjectContext;

final class GeneratePullRequestTest extends TestCase
{
    private string $tempDir;
    private SnapshotStore $store;
    private GeneratePullRequest $useCase;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/generate-pr-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);

        $this->store   = new SnapshotStore(new TestProjectContext($this->tempDir));
        $this->useCase = new GeneratePullRequest(
            $this->store,
            new NullLLMProvider(),
            new PromptBuilder(),
        );
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    // -------------------------------------------------------------------------
    // Fixture helpers
    // -------------------------------------------------------------------------

    private function savePlan(string $label, array $planOverrides = []): void
    {
        $plan = array_merge([
            'nodeId'             => 'App\\Service\\OrderService::process',
            'detectionReason'    => 'High fan-out and cyclomatic complexity indicate too many responsibilities.',
            'overallRisk'        => 'HIGH',
            'riskScore'          => 72,
            'estimatedComplexity'=> 7,
            'riskFactors'        => [],
            'prerequisites'      => [
                [
                    'type'          => 'cycle',
                    'description'   => 'Circular dependency with PaymentService',
                    'affectedNodes' => ['App\\Service\\PaymentService::charge'],
                    'severity'      => 'HIGH',
                    'recommendation'=> 'Extract an interface',
                ],
            ],
            'steps' => [
                [
                    'order'         => 1,
                    'action'        => 'Extract payment logic',
                    'target'        => 'App\\Service\\OrderService::process',
                    'rationale'     => 'Reduces fan-out',
                    'priority'      => 'HIGH',
                    'affectedNodes' => ['App\\Service\\PaymentService::charge', 'App\\Service\\OrderService::validate'],
                    'tests'         => ['tests/OrderServiceTest.php'],
                ],
                [
                    'order'         => 2,
                    'action'        => 'Add integration test',
                    'target'        => 'App\\Service\\OrderService::process',
                    'rationale'     => 'Safety net',
                    'priority'      => 'MEDIUM',
                    'affectedNodes' => [],
                    'tests'         => ['tests/OrderServiceIntegrationTest.php'],
                ],
            ],
            'testingGuidance' => ['Run full suite', 'Verify no regressions'],
            'metadata'        => [],
        ], $planOverrides);

        $this->store->save($label, [
            'type'    => 'refactor_plan',
            'plan'    => $plan,
            'savedAt' => '2026-02-19T10:00:00+00:00',
        ]);
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    public function test_execute_returns_dto_with_correct_fields(): void
    {
        $this->savePlan('my-plan');
        $dto = $this->useCase->execute('my-plan');

        $this->assertSame('App\\Service\\OrderService::process', $dto->nodeId);
        $this->assertSame('HIGH', $dto->riskLevel);
        $this->assertSame(72, $dto->riskScore);
        $this->assertSame(2, $dto->stepsCount);
        $this->assertSame(1, $dto->prerequisitesCount);
        $this->assertSame('my-plan', $dto->planLabel);
    }

    public function test_branch_name_sanitizes_node_id(): void
    {
        $this->savePlan('plan-b', ['nodeId' => 'App\\Service\\OrderService::process']);
        $dto = $this->useCase->execute('plan-b');

        $this->assertStringStartsWith('refactor/', $dto->branch);
        $this->assertDoesNotMatchRegularExpression('/[\\\\:]+/', $dto->branch);
        $this->assertMatchesRegularExpression('/^refactor\/[a-z0-9-]+$/', $dto->branch);
    }

    public function test_pr_title_contains_risk_and_step_count(): void
    {
        $this->savePlan('plan-title');
        $dto = $this->useCase->execute('plan-title');

        $this->assertStringContainsString('HIGH', $dto->title);
        $this->assertStringContainsString('2 steps', $dto->title);
    }

    public function test_pr_title_uses_singular_for_one_step(): void
    {
        $oneStep = [[
            'order' => 1, 'action' => 'Do thing', 'target' => 'A::b',
            'rationale' => 'x', 'priority' => 'LOW',
            'affectedNodes' => [], 'tests' => [],
        ]];
        $this->savePlan('plan-single', ['steps' => $oneStep]);
        $dto = $this->useCase->execute('plan-single');

        $this->assertStringContainsString('1 step', $dto->title);
        $this->assertStringNotContainsString('1 steps', $dto->title);
    }

    public function test_affected_nodes_deduplicated_across_steps(): void
    {
        $steps = [
            ['order' => 1, 'action' => 'A', 'target' => 'X::a', 'rationale' => '', 'priority' => 'LOW',
             'affectedNodes' => ['Shared::node', 'Only::first'], 'tests' => []],
            ['order' => 2, 'action' => 'B', 'target' => 'X::b', 'rationale' => '', 'priority' => 'LOW',
             'affectedNodes' => ['Shared::node', 'Only::second'], 'tests' => []],
        ];
        $this->savePlan('plan-dedup', ['steps' => $steps, 'prerequisites' => []]);
        $dto = $this->useCase->execute('plan-dedup');

        $this->assertCount(3, $dto->affectedNodes);
        $this->assertContains('Shared::node', $dto->affectedNodes);
        $this->assertContains('Only::first', $dto->affectedNodes);
        $this->assertContains('Only::second', $dto->affectedNodes);
    }

    public function test_body_contains_detection_reason(): void
    {
        $this->savePlan('plan-body');
        $dto = $this->useCase->execute('plan-body');

        $this->assertStringContainsString('High fan-out and cyclomatic complexity', $dto->body);
    }

    public function test_body_contains_prerequisites(): void
    {
        $this->savePlan('plan-prereqs');
        $dto = $this->useCase->execute('plan-prereqs');

        $this->assertStringContainsString('Circular dependency with PaymentService', $dto->body);
    }

    public function test_body_contains_checklist_steps(): void
    {
        $this->savePlan('plan-steps');
        $dto = $this->useCase->execute('plan-steps');

        $this->assertStringContainsString('Extract payment logic', $dto->body);
        $this->assertStringContainsString('Add integration test', $dto->body);
    }

    public function test_body_contains_testing_guidance(): void
    {
        $this->savePlan('plan-guidance');
        $dto = $this->useCase->execute('plan-guidance');

        $this->assertStringContainsString('Run full suite', $dto->body);
        $this->assertStringContainsString('Verify no regressions', $dto->body);
    }

    public function test_throws_on_non_refactor_plan_snapshot(): void
    {
        $this->store->save('not-a-plan', ['type' => 'metrics', 'data' => []]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches("/not a refactor plan/");
        $this->useCase->execute('not-a-plan');
    }

    public function test_throws_when_plan_label_does_not_exist(): void
    {
        $this->expectException(RuntimeException::class);
        $this->useCase->execute('nonexistent-plan');
    }

    public function test_toArray_and_toJson_consistent(): void
    {
        $this->savePlan('plan-serial');
        $dto = $this->useCase->execute('plan-serial');

        $this->assertSame($dto->toArray(), json_decode($dto->toJson(), true));
    }
}
