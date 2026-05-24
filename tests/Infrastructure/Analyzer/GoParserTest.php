<?php

namespace Tests\Infrastructure\Analyzer;

use FlowEngine\Domain\Flow\DefaultNodeFactory;
use FlowEngine\Infrastructure\Analyzer\GoParser;
use PHPUnit\Framework\TestCase;

final class GoParserTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/go-parser-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function createTempFile(string $name, string $content): string
    {
        $path = $this->tempDir . '/' . $name;
        file_put_contents($path, $content);
        return $path;
    }

    private function makeParser(): GoParser
    {
        return new GoParser(new DefaultNodeFactory());
    }

    // -------------------------------------------------------------------------

    public function test_detects_exported_package_function(): void
    {
        $file = $this->createTempFile('handlers.go', <<<'GO'
package main

func Hello(w http.ResponseWriter, r *http.Request) {
    fmt.Fprintln(w, "Hello")
}
GO);

        $result = $this->makeParser()->parse($file);

        $ids = array_map(fn($n) => $n->id(), $result['nodes']);
        self::assertContains('go:main::Hello', $ids);
    }

    public function test_detects_struct_method(): void
    {
        $file = $this->createTempFile('service.go', <<<'GO'
package users

type UserService struct{}

func (s *UserService) GetUser(id string) *User {
    return nil
}
GO);

        $result = $this->makeParser()->parse($file);

        $ids = array_map(fn($n) => $n->id(), $result['nodes']);
        self::assertContains('go:users.UserService::GetUser', $ids);
    }

    public function test_package_name_in_node_id(): void
    {
        $file = $this->createTempFile('api.go', <<<'GO'
package api

func HandleRequest(w http.ResponseWriter, r *http.Request) {
    return
}
GO);

        $result = $this->makeParser()->parse($file);

        $ids = array_map(fn($n) => $n->id(), $result['nodes']);
        self::assertContains('go:api::HandleRequest', $ids);
    }

    public function test_stores_http_handler_metadata(): void
    {
        $file = $this->createTempFile('router.go', <<<'GO'
package main

func main() {
    http.HandleFunc("/api/users", GetUsers)
}

func GetUsers(w http.ResponseWriter, r *http.Request) {
    fmt.Fprintln(w, "[]")
}
GO);

        $result = $this->makeParser()->parse($file);

        $nodeMap = [];
        foreach ($result['nodes'] as $node) {
            $nodeMap[$node->id()] = $node;
        }

        $node = $nodeMap['go:main::GetUsers'] ?? null;
        self::assertNotNull($node, 'Node GetUsers not found');
        self::assertNotNull($node->metadata());
        self::assertSame('/api/users', $node->metadata()['http_path']);
    }

    public function test_stores_gin_route_metadata(): void
    {
        $file = $this->createTempFile('gin_router.go', <<<'GO'
package main

func SetupRouter(r *gin.Engine) {
    r.GET("/products", ListProducts)
}

func ListProducts(c *gin.Context) {
    c.JSON(200, gin.H{})
}
GO);

        $result = $this->makeParser()->parse($file);

        $nodeMap = [];
        foreach ($result['nodes'] as $node) {
            $nodeMap[$node->id()] = $node;
        }

        $node = $nodeMap['go:main::ListProducts'] ?? null;
        self::assertNotNull($node, 'Node ListProducts not found');
        self::assertNotNull($node->metadata());
        self::assertSame('GET', $node->metadata()['http_method']);
        self::assertSame('/products', $node->metadata()['http_path']);
    }

    public function test_unexported_function_not_detected(): void
    {
        $file = $this->createTempFile('private.go', <<<'GO'
package main

func helper() string {
    return "private"
}

func anotherHelper(x int) int {
    return x * 2
}
GO);

        $result = $this->makeParser()->parse($file);

        self::assertSame([], $result['nodes'], 'Unexported functions should not become nodes');
    }

    public function test_handles_empty_file(): void
    {
        $file = $this->createTempFile('empty.go', '');

        $result = $this->makeParser()->parse($file);

        self::assertSame([], $result['nodes']);
        self::assertSame([], $result['edges']);
    }
}
