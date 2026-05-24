<?php

use PHPUnit\Framework\TestCase;
use FlowEngine\Application\UseCase\ScoreChangeRisk;
use FlowEngine\Application\DTO\ChangeRiskDTO;
use FlowEngine\Domain\Analysis\RiskScorer;
use FlowEngine\Domain\Flow\Edge;
use FlowEngine\Domain\Flow\Node;
use Tests\Support\InMemoryFlowRepository;

final class ScoreChangeRiskTest extends TestCase
{
    public function test_returns_change_risk_dto(): void
    {
        $repo = new InMemoryFlowRepository([
            new Node('App\\Service', 'handle', __FILE__, 1),
        ]);

        $useCase = new ScoreChangeRisk(
            new RiskScorer(),
            $repo
        );

        $result = $useCase->execute('App\\Service::handle');

        $this->assertInstanceOf(ChangeRiskDTO::class, $result);
        $this->assertSame('App\\Service::handle', $result->nodeId);
        $this->assertSame('LOW', $result->level);
    }

    public function test_highly_coupled_node_has_higher_risk(): void
    {
        $nodes = [
            new Node('App\\Core', 'process', __FILE__, 1),
            new Node('App\\A', 'call', __FILE__, 2),
            new Node('App\\B', 'call', __FILE__, 3),
            new Node('App\\C', 'call', __FILE__, 4),
            new Node('App\\D', 'call', __FILE__, 5),
            new Node('App\\E', 'call', __FILE__, 6),
        ];

        $edges = [
            new Edge('App\\A::call', 'App\\Core::process', 'process', 'method_call'),
            new Edge('App\\B::call', 'App\\Core::process', 'process', 'method_call'),
            new Edge('App\\C::call', 'App\\Core::process', 'process', 'method_call'),
            new Edge('App\\Core::process', 'App\\D::call', 'call', 'method_call'),
            new Edge('App\\Core::process', 'App\\E::call', 'call', 'method_call'),
        ];

        $repo = new InMemoryFlowRepository($nodes, $edges);
        $useCase = new ScoreChangeRisk(new RiskScorer(), $repo);

        $coreResult = $useCase->execute('App\\Core::process');
        $leafResult = $useCase->execute('App\\E::call');

        $this->assertGreaterThan($leafResult->score, $coreResult->score);
    }

    public function test_factors_are_present(): void
    {
        $repo = new InMemoryFlowRepository([
            new Node('App\\Service', 'handle', __FILE__, 1),
        ]);

        $useCase = new ScoreChangeRisk(new RiskScorer(), $repo);
        $result = $useCase->execute('App\\Service::handle');

        $this->assertNotEmpty($result->factors);
        $this->assertCount(6, $result->factors);
    }

    public function test_metrics_are_included(): void
    {
        $repo = new InMemoryFlowRepository([
            new Node('App\\Service', 'handle', __FILE__, 1),
        ]);

        $useCase = new ScoreChangeRisk(new RiskScorer(), $repo);
        $result = $useCase->execute('App\\Service::handle');

        $this->assertArrayHasKey('nodeId', $result->metrics);
        $this->assertArrayHasKey('fanIn', $result->metrics);
        $this->assertArrayHasKey('fanOut', $result->metrics);
        $this->assertArrayHasKey('blastRadius', $result->metrics);
    }

    public function test_result_serializes_to_json(): void
    {
        $repo = new InMemoryFlowRepository([
            new Node('App\\Service', 'handle', __FILE__, 1),
        ]);

        $useCase = new ScoreChangeRisk(new RiskScorer(), $repo);
        $result = $useCase->execute('App\\Service::handle');
        $json = $result->toJson();

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertSame('App\\Service::handle', $decoded['nodeId']);
    }
}
