<?php

namespace Tests\Integration;

use FlowEngine\Bootstrap\Container;
use FlowEngine\AI\Export\MarkdownFormatter;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end integration test for refactor guidance workflow (v3.2).
 *
 * Tests: generate plan → save → get guidance → validate → mark done
 */
class RefactorGuidanceWorkflowTest extends TestCase
{
    private string $fixtureDir;
    private ?string $planLabel = null;

    protected function setUp(): void
    {
        $this->fixtureDir = __DIR__ . '/../fixtures/simple-project';
        $this->assertDirectoryExists($this->fixtureDir);

        $this->planLabel = 'guidance-test-' . time();
    }

    protected function tearDown(): void
    {
        if ($this->planLabel === null) {
            return;
        }

        // Cleanup snapshots created during tests
        $snapshotDir = $this->fixtureDir . '/.flow-engine/state/snapshots';
        foreach ([$this->planLabel, $this->planLabel . '-progress'] as $label) {
            $file = $snapshotDir . '/' . $label . '.json.gz';
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    public function testFullGuidanceWorkflow(): void
    {
        $container = new Container($this->fixtureDir);
        $container->analyzeProject()->execute();

        // 1. Find a node to test with
        $nodeId = $this->firstFixtureNodeId($container);

        // 2. Generate and save a plan
        $plan = $container->generateRefactorPlan()->execute($nodeId);
        $this->assertNotNull($plan);
        $this->assertSame($nodeId, $plan->nodeId);

        $container->saveRefactorPlan()->execute($this->planLabel, $plan);

        $this->assertNotEmpty($plan->steps, 'Fixture node must produce at least one refactor step.');

        $firstStepOrder = $plan->steps[0]->order;

        $guidance = $container->getRefactorGuidance()->execute($this->planLabel, $firstStepOrder);

        $this->assertNotNull($guidance);
        $this->assertSame($nodeId, $guidance->nodeId);
        $this->assertSame($firstStepOrder, $guidance->stepOrder);
        $this->assertNotEmpty($guidance->stepAction);
        $this->assertIsArray($guidance->actionableInstructions);
        $this->assertIsArray($guidance->verificationChecklist);
        $this->assertIsString($guidance->estimatedEffort);
        $this->assertArrayHasKey('tokensUsed', $guidance->metadata);

        // 4. Verify toArray() and toJson()
        $array = $guidance->toArray();
        $this->assertArrayHasKey('nodeId', $array);
        $this->assertArrayHasKey('stepOrder', $array);
        $this->assertArrayHasKey('actionableInstructions', $array);

        $json = $guidance->toJson();
        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertSame($nodeId, $decoded['nodeId']);

        // 5. Validate step
        $validation = $container->validateRefactorStep()->execute($this->planLabel, $firstStepOrder);

        $this->assertNotNull($validation);
        $this->assertSame($nodeId, $validation->nodeId);
        $this->assertSame($firstStepOrder, $validation->stepOrder);
        $this->assertIsBool($validation->passed);
        $this->assertIsArray($validation->findings);
        $this->assertArrayHasKey('fanIn', $validation->currentMetrics);
        $this->assertArrayHasKey('fanOut', $validation->currentMetrics);
        $this->assertArrayHasKey('blastRadius', $validation->currentMetrics);
        $this->assertNotEmpty($validation->recommendation);

        // 6. Mark step as done
        $progress = $container->recordRefactorStepCompletion()->execute($this->planLabel, $firstStepOrder, 'done');

        $this->assertNotNull($progress);
        $this->assertSame($this->planLabel, $progress->planLabel);
        $this->assertSame($nodeId, $progress->nodeId);
        $this->assertSame(count($plan->steps), $progress->totalSteps);
        $this->assertSame(1, $progress->completedSteps);
        $this->assertNotEmpty($progress->savedAt);

        // Verify the done step status
        $doneStep = null;
        foreach ($progress->steps as $s) {
            if ($s['order'] === $firstStepOrder) {
                $doneStep = $s;
                break;
            }
        }
        $this->assertNotNull($doneStep);
        $this->assertSame('done', $doneStep['status']);
        $this->assertNotNull($doneStep['completedAt']);
    }

    public function testGuidanceMarkdownFormat(): void
    {
        $container = new Container($this->fixtureDir);
        $container->analyzeProject()->execute();

        $nodeId = $this->firstFixtureNodeId($container);

        $plan = $container->generateRefactorPlan()->execute($nodeId);
        $container->saveRefactorPlan()->execute($this->planLabel, $plan);

        $this->assertNotEmpty($plan->steps, 'Fixture node must produce at least one refactor step.');

        $firstStepOrder = $plan->steps[0]->order;
        $guidance = $container->getRefactorGuidance()->execute($this->planLabel, $firstStepOrder);

        $formatter = new MarkdownFormatter();
        $markdown = $formatter->formatRefactorGuidance($guidance);

        $this->assertStringContainsString("# Step {$firstStepOrder} Guidance:", $markdown);
        $this->assertStringContainsString('**Node:**', $markdown);
        $this->assertStringContainsString('**Estimated Effort:**', $markdown);
    }

    public function testValidateMarkdownFormat(): void
    {
        $container = new Container($this->fixtureDir);
        $container->analyzeProject()->execute();

        $nodeId = $this->firstFixtureNodeId($container);

        $plan = $container->generateRefactorPlan()->execute($nodeId);
        $container->saveRefactorPlan()->execute($this->planLabel, $plan);

        $this->assertNotEmpty($plan->steps, 'Fixture node must produce at least one refactor step.');

        $firstStepOrder = $plan->steps[0]->order;
        $validation = $container->validateRefactorStep()->execute($this->planLabel, $firstStepOrder);

        $formatter = new MarkdownFormatter();
        $markdown = $formatter->formatRefactorValidation($validation);

        $this->assertStringContainsString("# Step {$firstStepOrder} Validation:", $markdown);
        $this->assertStringContainsString('## Metrics Comparison', $markdown);
        $this->assertStringContainsString('## Recommendation', $markdown);
    }

    public function testInvalidStepNumberThrowsException(): void
    {
        $container = new Container($this->fixtureDir);
        $container->analyzeProject()->execute();

        $nodeId = $this->firstFixtureNodeId($container);

        $plan = $container->generateRefactorPlan()->execute($nodeId);
        $container->saveRefactorPlan()->execute($this->planLabel, $plan);

        $this->expectException(\Throwable::class);
        $container->getRefactorGuidance()->execute($this->planLabel, 9999);
    }

    private function firstFixtureNodeId(Container $container): string
    {
        $nodes = $container->getNodes()->execute();
        $this->assertNotEmpty($nodes, 'Fixture project must expose at least one node.');

        foreach ($nodes as $node) {
            if (($node->id ?? null) === 'Fixture\\InvoiceService::totalWithTax') {
                return $node->id;
            }
        }

        $nodeId = $nodes[0]->id ?? null;
        $this->assertIsString($nodeId, 'Fixture project must expose a valid node id.');
        $this->assertNotSame('', $nodeId, 'Fixture project node id must not be empty.');

        return $nodeId;
    }
}
