<?php

namespace Tests\Domain\Analysis;

use FlowEngine\Domain\Analysis\ArchitectureValidator;
use FlowEngine\Domain\Flow\Edge;
use FlowEngine\Domain\Flow\Flow;
use FlowEngine\Domain\Flow\Node;
use PHPUnit\Framework\TestCase;

final class ArchitectureValidatorTest extends TestCase
{
    public function test_clean_architecture_no_violations(): void
    {
        // Infrastructure → Application → Domain (correto)
        $nodes = [
            new Node('App\Domain\Entity', 'method', __FILE__, 1),
            new Node('App\Application\UseCase', 'method', __FILE__, 2),
            new Node('App\Infrastructure\Repository', 'method', __FILE__, 3),
        ];

        $edges = [
            new Edge('App\Application\UseCase::method', 'App\Domain\Entity::method', 'method'),
            new Edge('App\Infrastructure\Repository::method', 'App\Domain\Entity::method', 'method'),
            new Edge('App\Infrastructure\Repository::method', 'App\Application\UseCase::method', 'method'),
        ];

        $flow = new Flow($nodes, $edges);
        $validator = new ArchitectureValidator($flow);

        $violations = $validator->detectViolations();

        $this->assertEmpty($violations);
        $this->assertTrue($validator->isClean());
    }

    public function test_detects_domain_to_infrastructure_violation(): void
    {
        $nodes = [
            new Node('App\Domain\Entity', 'method', __FILE__, 1),
            new Node('App\Infrastructure\Repository', 'method', __FILE__, 2),
        ];

        $edges = [
            // Violação: Domain chamando Infrastructure
            new Edge('App\Domain\Entity::method', 'App\Infrastructure\Repository::method', 'method'),
        ];

        $flow = new Flow($nodes, $edges);
        $validator = new ArchitectureValidator($flow);

        $violations = $validator->detectViolations();

        $this->assertCount(1, $violations);
        $this->assertEquals('CRITICAL', $violations[0]['severity']);
        $this->assertEquals('Domain', $violations[0]['fromLayer']);
        $this->assertEquals('Infrastructure', $violations[0]['toLayer']);
    }

    public function test_detects_domain_to_application_violation(): void
    {
        $nodes = [
            new Node('App\Domain\Entity', 'method', __FILE__, 1),
            new Node('App\Application\Service', 'method', __FILE__, 2),
        ];

        $edges = [
            // Violação: Domain chamando Application
            new Edge('App\Domain\Entity::method', 'App\Application\Service::method', 'method'),
        ];

        $flow = new Flow($nodes, $edges);
        $validator = new ArchitectureValidator($flow);

        $violations = $validator->detectViolations();

        $this->assertCount(1, $violations);
        $this->assertEquals('CRITICAL', $violations[0]['severity']);
        $this->assertEquals('Domain', $violations[0]['fromLayer']);
        $this->assertEquals('Application', $violations[0]['toLayer']);
    }

    public function test_detects_application_to_infrastructure_violation(): void
    {
        $nodes = [
            new Node('App\Application\UseCase', 'method', __FILE__, 1),
            new Node('App\Infrastructure\Http', 'method', __FILE__, 2),
        ];

        $edges = [
            // Violação: Application chamando Infrastructure
            new Edge('App\Application\UseCase::method', 'App\Infrastructure\Http::method', 'method'),
        ];

        $flow = new Flow($nodes, $edges);
        $validator = new ArchitectureValidator($flow);

        $violations = $validator->detectViolations();

        $this->assertCount(1, $violations);
        $this->assertEquals('HIGH', $violations[0]['severity']);
        $this->assertEquals('Application', $violations[0]['fromLayer']);
        $this->assertEquals('Infrastructure', $violations[0]['toLayer']);
    }

    public function test_allows_infrastructure_to_domain(): void
    {
        $nodes = [
            new Node('App\Infrastructure\Repository', 'method', __FILE__, 1),
            new Node('App\Domain\Entity', 'method', __FILE__, 2),
        ];

        $edges = [
            // OK: Infrastructure pode chamar Domain
            new Edge('App\Infrastructure\Repository::method', 'App\Domain\Entity::method', 'method'),
        ];

        $flow = new Flow($nodes, $edges);
        $validator = new ArchitectureValidator($flow);

        $violations = $validator->detectViolations();

        $this->assertEmpty($violations);
    }

    public function test_allows_infrastructure_to_application(): void
    {
        $nodes = [
            new Node('App\Infrastructure\Controller', 'method', __FILE__, 1),
            new Node('App\Application\Service', 'method', __FILE__, 2),
        ];

        $edges = [
            // OK: Infrastructure pode chamar Application
            new Edge('App\Infrastructure\Controller::method', 'App\Application\Service::method', 'method'),
        ];

        $flow = new Flow($nodes, $edges);
        $validator = new ArchitectureValidator($flow);

        $violations = $validator->detectViolations();

        $this->assertEmpty($violations);
    }

    public function test_allows_application_to_domain(): void
    {
        $nodes = [
            new Node('App\Application\UseCase', 'method', __FILE__, 1),
            new Node('App\Domain\Service', 'method', __FILE__, 2),
        ];

        $edges = [
            // OK: Application pode chamar Domain
            new Edge('App\Application\UseCase::method', 'App\Domain\Service::method', 'method'),
        ];

        $flow = new Flow($nodes, $edges);
        $validator = new ArchitectureValidator($flow);

        $violations = $validator->detectViolations();

        $this->assertEmpty($violations);
    }

    public function test_stats(): void
    {
        $nodes = [
            new Node('App\Domain\A', 'method', __FILE__, 1),
            new Node('App\Infrastructure\B', 'method', __FILE__, 2),
            new Node('App\Application\C', 'method', __FILE__, 3),
        ];

        $edges = [
            new Edge('App\Domain\A::method', 'App\Infrastructure\B::method', 'method'), // CRITICAL
            new Edge('App\Application\C::method', 'App\Infrastructure\B::method', 'method'), // HIGH
        ];

        $flow = new Flow($nodes, $edges);
        $validator = new ArchitectureValidator($flow);

        $stats = $validator->stats();

        $this->assertEquals(2, $stats['totalViolations']);
        $this->assertEquals(1, $stats['bySeverity']['CRITICAL']);
        $this->assertEquals(1, $stats['bySeverity']['HIGH']);
    }

    public function test_deduplicates_equivalent_violations_from_duplicate_edges(): void
    {
        $nodes = [
            new Node('App\Application\UseCase', 'method', __FILE__, 1),
            new Node('App\Infrastructure\Http', 'method', __FILE__, 2),
        ];

        $edges = [
            new Edge('App\Application\UseCase::method', 'App\Infrastructure\Http::method', 'method'),
            new Edge('App\Application\UseCase::method', 'App\Infrastructure\Http::method', 'method'),
        ];

        $flow = new Flow($nodes, $edges);
        $validator = new ArchitectureValidator($flow);

        $violations = $validator->detectViolations();
        $stats = $validator->stats();

        $this->assertCount(1, $violations);
        $this->assertEquals(1, $stats['totalViolations']);
        $this->assertEquals(1, $stats['bySeverity']['HIGH']);
        $this->assertEquals(1, array_sum($stats['byType']));
    }

    public function test_layer_distribution(): void
    {
        $nodes = [
            new Node('App\Domain\A', 'method', __FILE__, 1),
            new Node('App\Domain\B', 'method', __FILE__, 2),
            new Node('App\Application\C', 'method', __FILE__, 3),
            new Node('App\Infrastructure\D', 'method', __FILE__, 4),
        ];

        $flow = new Flow($nodes, []);
        $validator = new ArchitectureValidator($flow);

        $distribution = $validator->layerDistribution();

        $this->assertEquals(2, $distribution['Domain']);
        $this->assertEquals(1, $distribution['Application']);
        $this->assertEquals(1, $distribution['Infrastructure']);
    }

    public function test_recognizes_alternative_layer_names(): void
    {
        // Core, Entity = Domain
        // Service, UseCase = Application
        // Repository, Controller = Infrastructure
        $nodes = [
            new Node('App\Core\Entity', 'method', __FILE__, 1),
            new Node('App\Service\BusinessLogic', 'method', __FILE__, 2),
            new Node('App\Repository\Data', 'method', __FILE__, 3),
        ];

        $flow = new Flow($nodes, []);
        $validator = new ArchitectureValidator($flow);

        $distribution = $validator->layerDistribution();

        $this->assertEquals(1, $distribution['Domain']); // Core
        $this->assertEquals(1, $distribution['Application']); // Service
        $this->assertEquals(1, $distribution['Infrastructure']); // Repository
    }
}
