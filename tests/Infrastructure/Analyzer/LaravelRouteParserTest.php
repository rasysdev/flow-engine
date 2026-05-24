<?php

namespace Tests\Infrastructure\Analyzer;

use FlowEngine\Infrastructure\Analyzer\LaravelRouteParser;
use PHPUnit\Framework\TestCase;

final class LaravelRouteParserTest extends TestCase
{
    public function test_it_extracts_array_syntax_routes(): void
    {
        $root = realpath(__DIR__ . '/../Fixtures/ExampleProject');
        self::assertNotFalse($root);

        $file = $root . '/routes/web.php';
        self::assertFileExists($file);

        $parser = new LaravelRouteParser();
        $routes = $parser->parse([$file]);

        // Verify array syntax routes [Controller::class, 'method']
        $adminRoute = $this->findRoute($routes, 'GET', '/admin');
        self::assertNotNull($adminRoute, 'GET /admin route not found');
        self::assertSame('App\\Http\\Controllers\\AdminController', $adminRoute['controller']);
        self::assertSame('index', $adminRoute['action']);

        $filterRoute = $this->findRoute($routes, 'POST', '/admin/filter');
        self::assertNotNull($filterRoute, 'POST /admin/filter route not found');
        self::assertSame('filter', $filterRoute['action']);
    }

    public function test_it_extracts_closure_routes(): void
    {
        $root = realpath(__DIR__ . '/../Fixtures/ExampleProject');
        self::assertNotFalse($root);

        $parser = new LaravelRouteParser();
        $routes = $parser->parse([$root . '/routes/web.php']);

        $loginRoute = $this->findRoute($routes, 'GET', '/login');
        self::assertNotNull($loginRoute, 'GET /login closure route not found');
        self::assertSame('Closure', $loginRoute['controller']);
        self::assertSame('-', $loginRoute['action']);
    }

    public function test_it_extracts_resource_routes(): void
    {
        $root = realpath(__DIR__ . '/../Fixtures/ExampleProject');
        self::assertNotFalse($root);

        $parser = new LaravelRouteParser();
        $routes = $parser->parse([$root . '/routes/web.php']);

        $indexRoute = $this->findRoute($routes, 'GET', 'clients');
        self::assertNotNull($indexRoute, 'Resource index route not found');
        self::assertSame('App\\Http\\Controllers\\ClientController', $indexRoute['controller']);
        self::assertSame('index', $indexRoute['action']);

        $storeRoute = $this->findRoute($routes, 'POST', 'clients');
        self::assertNotNull($storeRoute, 'Resource store route not found');
        self::assertSame('store', $storeRoute['action']);
    }

    public function test_it_resolves_use_imports(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'routes');
        file_put_contents($tmp, '<?php
use App\Http\Controllers\NetworkToolsController;

Route::get("/network-tools", [NetworkToolsController::class, "index"]);
');

        $parser = new LaravelRouteParser();
        $routes = $parser->parse([$tmp]);
        unlink($tmp);

        $route = $this->findRoute($routes, 'GET', '/network-tools');
        self::assertNotNull($route);
        self::assertSame('App\\Http\\Controllers\\NetworkToolsController', $route['controller']);
    }

    /**
     * @param array<int, array<string, string>> $routes
     * @return array<string, string>|null
     */
    private function findRoute(array $routes, string $method, string $uri): ?array
    {
        foreach ($routes as $route) {
            if ($route['method'] === $method && $route['uri'] === $uri) {
                return $route;
            }
        }
        return null;
    }
}
