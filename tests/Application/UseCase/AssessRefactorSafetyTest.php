<?php

use PHPUnit\Framework\TestCase;
use FlowEngine\Application\UseCase\AssessRefactorSafety;
use FlowEngine\Application\UseCase\AnalyzeImpact;
use FlowEngine\Application\DTO\SafetyAssessmentDTO;
use FlowEngine\Domain\Flow\Edge;
use FlowEngine\Domain\Flow\Node;
use Tests\Support\InMemoryFlowRepository;

final class AssessRefactorSafetyTest extends TestCase
{
    public function test_returns_safety_assessment_dto(): void
    {
        $repo = new InMemoryFlowRepository(
            [
                new Node('App\\Controller', 'index', __FILE__, 1),
                new Node('App\\Service', 'handle', __FILE__, 10),
                new Node('App\\Repository', 'find', __FILE__, 20),
            ],
            [
                new Edge('App\\Controller::index', 'App\\Service::handle', 'handle', 'method_call'),
                new Edge('App\\Service::handle', 'App\\Repository::find', 'find', 'method_call'),
            ]
        );

        $useCase = new AssessRefactorSafety(
            new AnalyzeImpact($repo),
            $repo
        );

        $result = $useCase->execute('App\\Service::handle');

        $this->assertInstanceOf(SafetyAssessmentDTO::class, $result);
        $this->assertSame('App\\Service::handle', $result->nodeId);
        $this->assertGreaterThan(0, $result->affectedNodes);
    }

    public function test_isolated_node_is_low_risk(): void
    {
        $repo = new InMemoryFlowRepository([
            new Node('App\\Isolated', 'run', __FILE__, 1),
        ]);

        $useCase = new AssessRefactorSafety(
            new AnalyzeImpact($repo),
            $repo
        );

        $result = $useCase->execute('App\\Isolated::run');

        $this->assertSame('LOW', $result->overallRisk);
        $this->assertSame(0, $result->affectedNodes);
        $this->assertEmpty($result->cyclesAffected);
        $this->assertEmpty($result->violationsAffected);
        $this->assertEmpty($result->potentialOrphans);
    }

    public function test_detects_potential_orphans(): void
    {
        $repo = new InMemoryFlowRepository(
            [
                new Node('App\\Service', 'handle', __FILE__, 1),
                new Node('App\\Leaf', 'doWork', __FILE__, 10),
            ],
            [
                new Edge('App\\Service::handle', 'App\\Leaf::doWork', 'doWork', 'method_call'),
            ]
        );

        $useCase = new AssessRefactorSafety(
            new AnalyzeImpact($repo),
            $repo
        );

        $result = $useCase->execute('App\\Service::handle');

        $this->assertContains('App\\Leaf::doWork', $result->potentialOrphans);
    }

    public function test_recommendations_are_generated(): void
    {
        $repo = new InMemoryFlowRepository(
            [
                new Node('App\\Service', 'handle', __FILE__, 1),
                new Node('App\\Dep', 'call', __FILE__, 2),
            ],
            [
                new Edge('App\\Service::handle', 'App\\Dep::call', 'call', 'method_call'),
            ]
        );

        $useCase = new AssessRefactorSafety(
            new AnalyzeImpact($repo),
            $repo
        );

        $result = $useCase->execute('App\\Service::handle');

        $this->assertNotEmpty($result->recommendations);
    }

    public function test_detects_cycles_in_affected_nodes(): void
    {
        $repo = new InMemoryFlowRepository(
            [
                new Node('App\\Service', 'handle', __FILE__, 1),
                new Node('App\\A', 'call', __FILE__, 2),
                new Node('App\\B', 'call', __FILE__, 3),
            ],
            [
                new Edge('App\\Service::handle', 'App\\A::call', 'call', 'method_call'),
                new Edge('App\\A::call', 'App\\B::call', 'call', 'method_call'),
                new Edge('App\\B::call', 'App\\A::call', 'call', 'method_call'),
            ]
        );

        $useCase = new AssessRefactorSafety(
            new AnalyzeImpact($repo),
            $repo
        );

        $result = $useCase->execute('App\\Service::handle');

        $this->assertNotEmpty($result->cyclesAffected);
    }

    public function test_result_serializes_to_json(): void
    {
        $repo = new InMemoryFlowRepository([
            new Node('App\\Service', 'handle', __FILE__, 1),
        ]);

        $useCase = new AssessRefactorSafety(
            new AnalyzeImpact($repo),
            $repo
        );

        $result = $useCase->execute('App\\Service::handle');
        $json = $result->toJson();

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertSame('App\\Service::handle', $decoded['nodeId']);
        $this->assertArrayHasKey('overallRisk', $decoded);
    }
}
