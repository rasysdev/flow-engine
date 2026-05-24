<?php

use PHPUnit\Framework\TestCase;
use FlowEngine\Application\UseCase\LookupNodesBySymbol;
use FlowEngine\Domain\Flow\Edge;
use FlowEngine\Domain\Flow\Node;
use Tests\Support\InMemoryFlowRepository;

final class LookupNodesBySymbolTest extends TestCase
{
    private function buildRepo(): InMemoryFlowRepository
    {
        $nodes = [
            new Node('App\\Http\\UserController', 'index',  '/app/UserController.php', 1),
            new Node('App\\Http\\UserController', 'store',  '/app/UserController.php', 10),
            new Node('App\\Http\\PostController', 'index',  '/app/PostController.php', 1),
            new Node('App\\Service\\UserService', 'find',   '/app/UserService.php', 1),
            new Node('App\\Service\\UserService', 'create', '/app/UserService.php', 20),
        ];
        $edges = [
            new Edge('App\\Http\\UserController::index', 'App\\Service\\UserService::find', 'find'),
        ];
        return new InMemoryFlowRepository($nodes, $edges);
    }

    public function test_empty_query_returns_no_matches(): void
    {
        $repo = $this->buildRepo();
        $dto = (new LookupNodesBySymbol($repo))->execute('');

        $this->assertSame('', $dto->query);
        $this->assertCount(0, $dto->matches);
    }

    public function test_exact_class_match(): void
    {
        $repo = $this->buildRepo();
        $dto = (new LookupNodesBySymbol($repo))->execute('UserController', 'class');

        $ids = array_column($dto->matches, 'id');
        $this->assertContains('App\\Http\\UserController', $ids);
    }

    public function test_case_insensitive_match(): void
    {
        $repo = $this->buildRepo();
        $dto = (new LookupNodesBySymbol($repo))->execute('usercontroller', 'class');

        $this->assertNotEmpty($dto->matches);
        $ids = array_column($dto->matches, 'id');
        $this->assertContains('App\\Http\\UserController', $ids);
    }

    public function test_partial_substring_match(): void
    {
        $repo = $this->buildRepo();
        $dto = (new LookupNodesBySymbol($repo))->execute('Controller', 'class');

        $ids = array_column($dto->matches, 'id');
        $this->assertContains('App\\Http\\UserController', $ids);
        $this->assertContains('App\\Http\\PostController', $ids);
    }

    public function test_filter_by_type_class(): void
    {
        $repo = $this->buildRepo();
        $dto = (new LookupNodesBySymbol($repo))->execute('user', 'class');

        foreach ($dto->matches as $match) {
            $this->assertSame('class', $match['type']);
        }
        $ids = array_column($dto->matches, 'id');
        $this->assertContains('App\\Http\\UserController', $ids);
        $this->assertContains('App\\Service\\UserService', $ids);
    }

    public function test_filter_by_type_method(): void
    {
        $repo = $this->buildRepo();
        $dto = (new LookupNodesBySymbol($repo))->execute('index', 'method');

        $this->assertNotEmpty($dto->matches);
        foreach ($dto->matches as $match) {
            $this->assertSame('method', $match['type']);
        }
    }

    public function test_filter_by_type_namespace(): void
    {
        $repo = $this->buildRepo();
        $dto = (new LookupNodesBySymbol($repo))->execute('Service', 'namespace');

        $this->assertNotEmpty($dto->matches);
        foreach ($dto->matches as $match) {
            $this->assertSame('namespace', $match['type']);
        }
    }

    public function test_limit_is_respected(): void
    {
        $nodes = [];
        for ($i = 0; $i < 20; $i++) {
            $nodes[] = new Node('App\\Http\\Controller' . $i, 'index', '/f', $i);
        }
        $repo = new InMemoryFlowRepository($nodes);
        $dto = (new LookupNodesBySymbol($repo))->execute('controller', 'class', 5);

        $this->assertLessThanOrEqual(5, count($dto->matches));
    }

    public function test_class_match_includes_methods_list(): void
    {
        $repo = $this->buildRepo();
        $dto = (new LookupNodesBySymbol($repo))->execute('UserController', 'class');

        $match = null;
        foreach ($dto->matches as $m) {
            if ($m['id'] === 'App\\Http\\UserController') {
                $match = $m;
                break;
            }
        }

        $this->assertNotNull($match);
        $this->assertArrayHasKey('methods', $match);
        $this->assertContains('index', $match['methods']);
        $this->assertContains('store', $match['methods']);
    }

    public function test_fan_in_fan_out_are_present(): void
    {
        $repo = $this->buildRepo();
        $dto = (new LookupNodesBySymbol($repo))->execute('UserController', 'class');

        $match = $dto->matches[0] ?? null;
        $this->assertNotNull($match);
        $this->assertArrayHasKey('fan_in', $match);
        $this->assertArrayHasKey('fan_out', $match);
        $this->assertIsInt($match['fan_in']);
        $this->assertIsInt($match['fan_out']);
    }

    public function test_to_json_returns_valid_json(): void
    {
        $repo = $this->buildRepo();
        $dto = (new LookupNodesBySymbol($repo))->execute('User');

        $json = $dto->toJson();
        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertArrayHasKey('query', $decoded);
        $this->assertArrayHasKey('matches', $decoded);
    }

    public function test_no_type_filter_returns_mixed_types(): void
    {
        $repo = $this->buildRepo();
        // 'index' matches as method, but no class/namespace named 'index'
        $dto = (new LookupNodesBySymbol($repo))->execute('index');

        $types = array_unique(array_column($dto->matches, 'type'));
        $this->assertContains('method', $types);
    }
}
