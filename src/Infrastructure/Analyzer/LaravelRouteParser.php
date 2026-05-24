<?php

namespace FlowEngine\Infrastructure\Analyzer;

/**
 * Regex-based parser for Laravel route files (routes/*.php).
 *
 * Detects:
 * - Route::get('/path', [Controller::class, 'method'])
 * - Route::post('/path', 'Controller@method') (legacy)
 * - Route::resource('resource', Controller::class)
 * - Route::apiResource('resource', Controller::class)
 * - Closure routes (URI only, controller = "Closure")
 *
 * @internal
 */
final class LaravelRouteParser
{
    /**
     * @param string[] $files Absolute paths to route files
     * @return array<int, array{method: string, uri: string, controller: string, action: string}>
     */
    public function parse(array $files): array
    {
        $routes = [];

        foreach ($files as $file) {
            $content = @file_get_contents($file);
            if ($content === false) {
                continue;
            }

            $useImports = $this->resolveUseImports($content);
            $routes = array_merge($routes, $this->extractRoutes($content, $useImports));
        }

        return $routes;
    }

    /**
     * @return array<string, string> shortName => FQN
     */
    private function resolveUseImports(string $content): array
    {
        $imports = [];
        if (preg_match_all('/^use\s+([A-Za-z0-9_\\\\]+)(?:\s+as\s+([A-Za-z0-9_]+))?;/m', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $fqn = $m[1];
                $alias = $m[2] ?? null;

                if ($alias !== null) {
                    $imports[$alias] = $fqn;
                } else {
                    $short = substr($fqn, (int) strrpos($fqn, '\\') + 1);
                    $imports[$short] = $fqn;
                }
            }
        }

        return $imports;
    }

    /**
     * @param array<string, string> $useImports
     * @return array<int, array{method: string, uri: string, controller: string, action: string}>
     */
    private function extractRoutes(string $content, array $useImports): array
    {
        $routes = [];

        // Pattern 1: Route::method('/path', [Controller::class, 'action'])
        $arrayPattern = '/Route\s*::\s*(get|post|put|patch|delete|options|any|match)\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*\[\s*([A-Za-z0-9_\\\\]+)\s*::\s*class\s*,\s*[\'"]([^\'"]+)[\'"]\s*\]/i';
        if (preg_match_all($arrayPattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $controller = $this->resolveController($m[3], $useImports);
                $routes[] = [
                    'method' => strtoupper($m[1]),
                    'uri' => $m[2],
                    'controller' => $controller,
                    'action' => $m[4],
                ];
            }
        }

        // Pattern 2: Route::method('/path', 'Controller@action') (legacy)
        $legacyPattern = '/Route\s*::\s*(get|post|put|patch|delete|options|any|match)\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]([A-Za-z0-9_\\\\]+)@([A-Za-z0-9_]+)[\'"]\s*\)/i';
        if (preg_match_all($legacyPattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $controller = $this->resolveController($m[3], $useImports);
                $routes[] = [
                    'method' => strtoupper($m[1]),
                    'uri' => $m[2],
                    'controller' => $controller,
                    'action' => $m[4],
                ];
            }
        }

        // Pattern 3: Route::resource('name', Controller::class)
        $resourcePattern = '/Route\s*::\s*(resource|apiResource)\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*([A-Za-z0-9_\\\\]+)\s*::\s*class\s*\)/i';
        if (preg_match_all($resourcePattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $type = strtolower($m[1]);
                $uri = $m[2];
                $controller = $this->resolveController($m[3], $useImports);

                if ($type === 'apiresource') {
                    $routes[] = ['method' => 'GET', 'uri' => $uri, 'controller' => $controller, 'action' => 'index'];
                    $routes[] = ['method' => 'POST', 'uri' => $uri, 'controller' => $controller, 'action' => 'store'];
                    $routes[] = ['method' => 'GET', 'uri' => $uri . '/{' . rtrim($uri, 's') . '}', 'controller' => $controller, 'action' => 'show'];
                    $routes[] = ['method' => 'PUT', 'uri' => $uri . '/{' . rtrim($uri, 's') . '}', 'controller' => $controller, 'action' => 'update'];
                    $routes[] = ['method' => 'DELETE', 'uri' => $uri . '/{' . rtrim($uri, 's') . '}', 'controller' => $controller, 'action' => 'destroy'];
                } else {
                    $routes[] = ['method' => 'GET', 'uri' => $uri, 'controller' => $controller, 'action' => 'index'];
                    $routes[] = ['method' => 'GET', 'uri' => $uri . '/create', 'controller' => $controller, 'action' => 'create'];
                    $routes[] = ['method' => 'POST', 'uri' => $uri, 'controller' => $controller, 'action' => 'store'];
                    $routes[] = ['method' => 'GET', 'uri' => $uri . '/{' . rtrim($uri, 's') . '}', 'controller' => $controller, 'action' => 'show'];
                    $routes[] = ['method' => 'GET', 'uri' => $uri . '/{' . rtrim($uri, 's') . '}/edit', 'controller' => $controller, 'action' => 'edit'];
                    $routes[] = ['method' => 'PUT', 'uri' => $uri . '/{' . rtrim($uri, 's') . '}', 'controller' => $controller, 'action' => 'update'];
                    $routes[] = ['method' => 'DELETE', 'uri' => $uri . '/{' . rtrim($uri, 's') . '}', 'controller' => $controller, 'action' => 'destroy'];
                }
            }
        }

        // Pattern 5: Closure routes - Route::method('/path', function ...) or Route::method('/path', fn() ...)
        $closurePattern = '/Route\s*::\s*(get|post|put|patch|delete|options|any)\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*(?:function\b|fn\s*\()/i';
        if (preg_match_all($closurePattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $routes[] = [
                    'method' => strtoupper($m[1]),
                    'uri' => $m[2],
                    'controller' => 'Closure',
                    'action' => '-',
                ];
            }
        }

        return $routes;
    }

    /**
     * @param array<string, string> $useImports
     */
    private function resolveController(string $name, array $useImports): string
    {
        // Already FQN
        if (str_contains($name, '\\')) {
            return ltrim($name, '\\');
        }

        return $useImports[$name] ?? $name;
    }
}
