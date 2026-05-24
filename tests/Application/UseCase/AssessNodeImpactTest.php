<?php

use PHPUnit\Framework\TestCase;
use FlowEngine\Application\UseCase\AssessNodeImpact;
use FlowEngine\Application\UseCase\AnalyzeImpact;
use FlowEngine\Application\DTO\NodeImpactReportDTO;
use FlowEngine\Domain\Analysis\RiskScorer;
use FlowEngine\Domain\Flow\Edge;
use FlowEngine\Domain\Flow\Node;
use Tests\Support\InMemoryFlowRepository;

final class AssessNodeImpactTest extends TestCase
{
    public function test_returns_node_impact_report(): void
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

        $useCase = new AssessNodeImpact(
            new AnalyzeImpact($repo),
            new RiskScorer(),
            $repo
        );

        $result = $useCase->execute('App\\Service::handle');

        $this->assertInstanceOf(NodeImpactReportDTO::class, $result);
        $this->assertSame('App\\Service::handle', $result->nodeId);
        $this->assertContains('App\\Controller::index', $result->upstream);
        $this->assertContains('App\\Repository::find', $result->downstream);
        $this->assertSame(1, $result->fanIn);
        $this->assertSame(1, $result->fanOut);
    }

    public function test_isolated_node_has_low_risk(): void
    {
        $repo = new InMemoryFlowRepository([
            new Node('App\\Isolated', 'run', __FILE__, 1),
        ]);

        $useCase = new AssessNodeImpact(
            new AnalyzeImpact($repo),
            new RiskScorer(),
            $repo
        );

        $result = $useCase->execute('App\\Isolated::run');

        $this->assertSame('LOW', $result->riskLevel);
        $this->assertEmpty($result->upstream);
        $this->assertEmpty($result->downstream);
        $this->assertEmpty($result->cyclesInvolved);
        $this->assertEmpty($result->violationsInvolved);
    }

    public function test_node_in_cycle_reports_cycle(): void
    {
        $repo = new InMemoryFlowRepository(
            [
                new Node('App\\A', 'call', __FILE__, 1),
                new Node('App\\B', 'call', __FILE__, 2),
            ],
            [
                new Edge('App\\A::call', 'App\\B::call', 'call', 'method_call'),
                new Edge('App\\B::call', 'App\\A::call', 'call', 'method_call'),
            ]
        );

        $useCase = new AssessNodeImpact(
            new AnalyzeImpact($repo),
            new RiskScorer(),
            $repo
        );

        $result = $useCase->execute('App\\A::call');

        $this->assertNotEmpty($result->cyclesInvolved);
    }

    public function test_result_serializes_to_json(): void
    {
        $repo = new InMemoryFlowRepository([
            new Node('App\\Service', 'handle', __FILE__, 1),
        ]);

        $useCase = new AssessNodeImpact(
            new AnalyzeImpact($repo),
            new RiskScorer(),
            $repo
        );

        $result = $useCase->execute('App\\Service::handle');
        $json = $result->toJson();

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertSame('App\\Service::handle', $decoded['nodeId']);
        $this->assertArrayHasKey('riskSummary', $decoded);
    }
}
