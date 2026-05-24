<?php

namespace Tests\Unit\Application\UseCase;

use FlowEngine\Application\DTO\RefactorPlanDTO;
use FlowEngine\Application\DTO\RefactorPrerequisiteDTO;
use FlowEngine\Application\DTO\RefactorStepDTO;
use FlowEngine\AI\Export\MarkdownFormatter;
use FlowEngine\AI\Prompt\InterpretationPrompts;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the refactor plan feature.
 *
 * Note: GenerateRefactorPlan use case depends on final classes (AssessNodeImpact,
 * AssessRefactorSafety, ScoreChangeRisk) that cannot be mocked with PHPUnit.
 * End-to-end workflow is covered in RefactorPlanWorkflowTest (integration test).
 *
 * These unit tests cover: DTO serialization, formatting, and prompt template.
 */
class GenerateRefactorPlanTest extends TestCase
{
    // ──────────────────────────────────────────────────────────────────────────
    // RefactorStepDTO
    // ──────────────────────────────────────────────────────────────────────────

    public function testRefactorStepDTOConstructorSetsProperties(): void
    {
        $step = new RefactorStepDTO(
            order: 1,
            action: 'Extract interface',
            target: 'MyClass::myMethod',
            rationale: 'Reduce coupling',
            priority: 'HIGH',
            affectedNodes: ['CallerA::call', 'CallerB::call'],
            tests: ['tests/MyClassTest.php']
        );

        $this->assertSame(1, $step->order);
        $this->assertSame('Extract interface', $step->action);
        $this->assertSame('MyClass::myMethod', $step->target);
        $this->assertSame('Reduce coupling', $step->rationale);
        $this->assertSame('HIGH', $step->priority);
        $this->assertCount(2, $step->affectedNodes);
        $this->assertCount(1, $step->tests);
    }

    public function testRefactorStepDTOToArraySerializesCorrectly(): void
    {
        $step = new RefactorStepDTO(
            order: 2,
            action: 'Move class',
            target: 'OldNs\\MyClass::method',
            rationale: 'Layer compliance',
            priority: 'CRITICAL',
            affectedNodes: [],
            tests: []
        );

        $array = $step->toArray();

        $this->assertArrayHasKey('order', $array);
        $this->assertArrayHasKey('action', $array);
        $this->assertArrayHasKey('target', $array);
        $this->assertArrayHasKey('rationale', $array);
        $this->assertArrayHasKey('priority', $array);
        $this->assertArrayHasKey('affectedNodes', $array);
        $this->assertArrayHasKey('tests', $array);
        $this->assertSame(2, $array['order']);
        $this->assertSame('CRITICAL', $array['priority']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // RefactorPrerequisiteDTO
    // ──────────────────────────────────────────────────────────────────────────

    public function testRefactorPrerequisiteDTOToArray(): void
    {
        $prereq = new RefactorPrerequisiteDTO(
            type: 'cycle',
            description: 'Circular dependency between A and B',
            affectedNodes: ['A::m1', 'B::m2'],
            severity: 'HIGH',
            recommendation: 'Extract interface'
        );

        $array = $prereq->toArray();

        $this->assertSame('cycle', $array['type']);
        $this->assertSame('Circular dependency between A and B', $array['description']);
        $this->assertSame(['A::m1', 'B::m2'], $array['affectedNodes']);
        $this->assertSame('HIGH', $array['severity']);
        $this->assertSame('Extract interface', $array['recommendation']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // RefactorPlanDTO
    // ──────────────────────────────────────────────────────────────────────────

    public function testRefactorPlanDTOToArraySerializesCorrectly(): void
    {
        $plan = $this->buildSamplePlan();

        $array = $plan->toArray();

        $this->assertSame('MyClass::myMethod', $array['nodeId']);
        $this->assertSame('HIGH', $array['overallRisk']);
        $this->assertSame(75, $array['riskScore']);
        $this->assertCount(1, $array['prerequisites']);
        $this->assertCount(1, $array['steps']);
        $this->assertCount(1, $array['testingGuidance']);
        $this->assertSame(6, $array['estimatedComplexity']);
        $this->assertArrayHasKey('metadata', $array);
    }

    public function testRefactorPlanDTOToJsonIsValidJson(): void
    {
        $plan = $this->buildSamplePlan();

        $json = $plan->toJson();

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertSame('MyClass::myMethod', $decoded['nodeId']);
    }

    public function testRefactorPlanDTOPrerequisitesSerializedAsArrays(): void
    {
        $plan = $this->buildSamplePlan();
        $array = $plan->toArray();

        $prereq = $array['prerequisites'][0];
        $this->assertIsArray($prereq);
        $this->assertArrayHasKey('type', $prereq);
        $this->assertArrayHasKey('severity', $prereq);
    }

    public function testRefactorPlanDTOStepsSerializedAsArrays(): void
    {
        $plan = $this->buildSamplePlan();
        $array = $plan->toArray();

        $step = $array['steps'][0];
        $this->assertIsArray($step);
        $this->assertArrayHasKey('order', $step);
        $this->assertArrayHasKey('action', $step);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // MarkdownFormatter
    // ──────────────────────────────────────────────────────────────────────────

    public function testMarkdownFormatterProducesValidMarkdown(): void
    {
        $formatter = new MarkdownFormatter();
        $plan = $this->buildSamplePlan();

        $markdown = $formatter->formatRefactorPlan($plan);

        $this->assertStringContainsString('# Refactoring Plan: MyClass::myMethod', $markdown);
        $this->assertStringContainsString('## Why Refactor?', $markdown);
        $this->assertStringContainsString('## Risk Assessment', $markdown);
        $this->assertStringContainsString('HIGH', $markdown);
        $this->assertStringContainsString('75 / 100', $markdown);
        $this->assertStringContainsString('## Prerequisites', $markdown);
        $this->assertStringContainsString('## Refactoring Steps', $markdown);
        $this->assertStringContainsString('## Testing Guidance', $markdown);
    }

    public function testMarkdownFormatterTrivialNodeOutput(): void
    {
        $formatter = new MarkdownFormatter();
        $trivialPlan = new RefactorPlanDTO(
            nodeId: 'SimpleClass::simpleMethod',
            detectionReason: 'Minimal complexity.',
            overallRisk: 'LOW',
            riskScore: 2,
            riskFactors: [],
            prerequisites: [],
            steps: [],
            testingGuidance: [],
            estimatedComplexity: 1,
            metadata: ['tokensUsed' => 0, 'trivial' => true]
        );

        $markdown = $formatter->formatRefactorPlan($trivialPlan);

        $this->assertStringContainsString('trivial node, no LLM call', $markdown);
        $this->assertStringContainsString('No blocking issues detected', $markdown);
        $this->assertStringContainsString('No specific steps required', $markdown);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // InterpretationPrompts
    // ──────────────────────────────────────────────────────────────────────────

    public function testRefactorPlanPromptTemplateExists(): void
    {
        $template = InterpretationPrompts::refactorPlan();

        $this->assertNotNull($template);
        $this->assertSame('Refactoring Plan Generation', $template->title);
        $this->assertStringContainsString('detectionReason', $template->body);
        $this->assertStringContainsString('prerequisites', $template->body);
        $this->assertStringContainsString('steps', $template->body);
        $this->assertStringContainsString('estimatedComplexity', $template->body);
    }

    public function testRefactorPlanPromptInAllMap(): void
    {
        $all = InterpretationPrompts::all();

        $this->assertArrayHasKey('refactorPlan', $all);
        $this->assertIsCallable($all['refactorPlan']);

        $template = ($all['refactorPlan'])();
        $this->assertNotNull($template);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function buildSamplePlan(): RefactorPlanDTO
    {
        $prereq = new RefactorPrerequisiteDTO(
            type: 'cycle',
            description: 'Circular dependency detected',
            affectedNodes: ['MyClass::myMethod', 'OtherClass::otherMethod'],
            severity: 'HIGH',
            recommendation: 'Extract interface to break cycle'
        );

        $step = new RefactorStepDTO(
            order: 1,
            action: 'Extract interface',
            target: 'MyClass::myMethod',
            rationale: 'Reduce coupling and break cycle',
            priority: 'HIGH',
            affectedNodes: ['MyClass::myMethod'],
            tests: ['tests/MyClassTest.php']
        );

        return new RefactorPlanDTO(
            nodeId: 'MyClass::myMethod',
            detectionReason: 'High complexity and cyclic dependency detected.',
            overallRisk: 'HIGH',
            riskScore: 75,
            riskFactors: [
                ['name' => 'complexity', 'weight' => 0.3, 'value' => 75.0, 'contribution' => 22.5],
            ],
            prerequisites: [$prereq],
            steps: [$step],
            testingGuidance: ['Verify all callers after refactoring.'],
            estimatedComplexity: 6,
            metadata: ['tokensUsed' => 500, 'trivial' => false]
        );
    }
}
