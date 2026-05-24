<?php

use PHPUnit\Framework\TestCase;
use FlowEngine\Application\UseCase\MapProjectStructure;
use FlowEngine\Domain\Flow\Edge;
use FlowEngine\Domain\Flow\Node;
use Tests\Support\InMemoryFlowRepository;

final class MapProjectStructureTest extends TestCase
{
    public function test_dto_has_expected_fields(): void
    {
        $repo = $this->buildRepo();
        $dto = (new MapProjectStructure($repo))->execute();

        $this->assertIsString($dto->project);
        $this->assertIsString($dto->language);
        $this->assertIsArray($dto->stats);
        $this->assertArrayHasKey('nodes', $dto->stats);
        $this->assertArrayHasKey('edges', $dto->stats);
        $this->assertArrayHasKey('cycles', $dto->stats);
        $this->assertIsArray($dto->top_namespaces);
        $this->assertIsArray($dto->entrypoints);
        $this->assertIsArray($dto->hotspots_top5);
    }

    public function test_language_detection_php(): void
    {
        $repo = $this->buildRepo();
        $dto = (new MapProjectStructure($repo))->execute();
        $this->assertSame('php', $dto->language);
    }

    public function test_stats_nodes_and_edges(): void
    {
        $nodes = [
            new Node('App\\Http\\UserController', 'index', '/app/Http/UserController.php', 1),
            new Node('App\\Http\\UserController', 'store', '/app/Http/UserController.php', 10),
            new Node('App\\Service\\UserService', 'find', '/app/Service/UserService.php', 1),
        ];
        $edges = [
            new Edge('App\\Http\\UserController::index', 'App\\Service\\UserService::find', 'find'),
        ];
        $repo = new InMemoryFlowRepository($nodes, $edges);
        $dto = (new MapProjectStructure($repo))->execute();

        $this->assertSame(3, $dto->stats['nodes']);
        $this->assertSame(1, $dto->stats['edges']);
    }

    public function test_top_namespaces_groups_by_two_segments(): void
    {
        $nodes = [
            new Node('App\\Http\\UserController', 'index', '/f', 1),
            new Node('App\\Http\\PostController', 'index', '/f', 2),
            new Node('App\\Service\\UserService', 'find', '/f', 3),
        ];
        $repo = new InMemoryFlowRepository($nodes);
        $dto = (new MapProjectStructure($repo))->execute();

        $nsByName = [];
        foreach ($dto->top_namespaces as $entry) {
            $nsByName[$entry['namespace']] = $entry['classes'];
        }

        $this->assertArrayHasKey('App\\Http', $nsByName);
        $this->assertSame(2, $nsByName['App\\Http']);
        $this->assertArrayHasKey('App\\Service', $nsByName);
        $this->assertSame(1, $nsByName['App\\Service']);
    }

    public function test_entrypoints_are_nodes_with_no_incoming_edges(): void
    {
        $nodes = [
            new Node('App\\Http\\Controller', 'index', '/f', 1),
            new Node('App\\Service\\Service', 'handle', '/f', 2),
        ];
        // Controller::index calls Service::handle
        $edges = [
            new Edge('App\\Http\\Controller::index', 'App\\Service\\Service::handle', 'handle'),
        ];
        $repo = new InMemoryFlowRepository($nodes, $edges);
        $dto = (new MapProjectStructure($repo))->execute();

        $this->assertContains('App\\Http\\Controller::index', $dto->entrypoints);
        $this->assertNotContains('App\\Service\\Service::handle', $dto->entrypoints);
    }

    public function test_to_json_returns_valid_json(): void
    {
        $repo = $this->buildRepo();
        $dto = (new MapProjectStructure($repo))->execute();
        $json = $dto->toJson();

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertArrayHasKey('project', $decoded);
        $this->assertArrayHasKey('language', $decoded);
        $this->assertArrayHasKey('stats', $decoded);
    }

    public function test_to_json_stays_under_1500_bytes(): void
    {
        $nodes = [];
        for ($i = 0; $i < 30; $i++) {
            $nodes[] = new Node('App\\Http\\Controller' . $i, 'index', '/very/long/path/to/file/Controller.php', $i);
        }
        $repo = new InMemoryFlowRepository($nodes);
        $dto = (new MapProjectStructure($repo))->execute();

        $this->assertLessThanOrEqual(1500, strlen($dto->toJson()));
    }

    public function test_truncation_reduces_entrypoints_and_namespaces(): void
    {
        // Build enough nodes to exceed 1500 chars before truncation
        $nodes = [];
        for ($i = 0; $i < 25; $i++) {
            $class = 'App\\Namespace' . $i . '\\Controller' . $i . 'LongSuffix';
            $nodes[] = new Node($class, 'indexActionMethodLong', '/very/long/absolute/path/to/project/src/Controller.php', $i);
        }
        $repo = new InMemoryFlowRepository($nodes);
        $dto = (new MapProjectStructure($repo))->execute();
        $json = $dto->toJson();

        $this->assertLessThanOrEqual(1500, strlen($json));
    }

    public function test_framework_is_null_without_project_root(): void
    {
        $repo = $this->buildRepo();
        $dto = (new MapProjectStructure($repo, ''))->execute();
        $this->assertNull($dto->framework);
    }

    // -----------------------------------------------------------------------

    private function buildRepo(): InMemoryFlowRepository
    {
        return new InMemoryFlowRepository([
            new Node('App\\Http\\UserController', 'index', '/app/Http/UserController.php', 1),
            new Node('App\\Service\\UserService', 'find', '/app/Service/UserService.php', 2),
        ]);
    }
}
