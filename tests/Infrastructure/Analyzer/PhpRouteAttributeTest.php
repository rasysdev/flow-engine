<?php

namespace Tests\Infrastructure\Analyzer;

use FlowEngine\Domain\Flow\DefaultNodeFactory;
use FlowEngine\Domain\Flow\Node;
use FlowEngine\Infrastructure\Analyzer\AstParser;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests verifying that PHP 8 route attributes (#[Route], #[Get], etc.)
 * are extracted into http_path / http_method node metadata so that
 * CrossLanguageEdgeDetector can match them to TypeScript/JS fetch() calls.
 *
 * @covers \FlowEngine\Infrastructure\Analyzer\Visitors\ClassVisitor
 * @covers \FlowEngine\Infrastructure\Analyzer\Visitors\MethodVisitor
 */
final class PhpRouteAttributeTest extends TestCase
{
    private AstParser $parser;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->parser  = new AstParser(new DefaultNodeFactory());
        $this->tempDir = sys_get_temp_dir() . '/php-route-attr-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            array_map('unlink', glob($this->tempDir . '/*') ?: []);
            rmdir($this->tempDir);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function parsePhp(string $code): array
    {
        $file = $this->tempDir . '/Test' . uniqid() . '.php';
        file_put_contents($file, $code);
        return $this->parser->parse($file);
    }

    /** @param Node[] $nodes */
    private function findNode(array $nodes, string $method): ?Node
    {
        foreach ($nodes as $node) {
            if ($node->method() === $method) {
                return $node;
            }
        }
        return null;
    }

    // -------------------------------------------------------------------------
    // Method-only route attributes
    // -------------------------------------------------------------------------

    public function test_get_attribute_sets_http_path_and_method(): void
    {
        $result = $this->parsePhp(<<<'PHP'
        <?php
        namespace App\Controller;

        class UserController
        {
            #[\Symfony\Component\Routing\Attribute\Get('/api/users')]
            public function list(): void {}
        }
        PHP);

        $node = $this->findNode($result['nodes'], 'list');
        $this->assertNotNull($node, 'Node for list() not found');
        $meta = $node->metadata();
        $this->assertSame('/api/users', $meta['http_path'] ?? null);
        $this->assertSame('GET', $meta['http_method'] ?? null);
    }

    public function test_post_attribute_sets_http_method_post(): void
    {
        $result = $this->parsePhp(<<<'PHP'
        <?php
        namespace App\Controller;

        class UserController
        {
            #[Post('/api/users')]
            public function create(): void {}
        }
        PHP);

        $node = $this->findNode($result['nodes'], 'create');
        $this->assertNotNull($node);
        $meta = $node->metadata();
        $this->assertSame('/api/users', $meta['http_path'] ?? null);
        $this->assertSame('POST', $meta['http_method'] ?? null);
    }

    public function test_delete_attribute_sets_http_method_delete(): void
    {
        $result = $this->parsePhp(<<<'PHP'
        <?php
        namespace App\Controller;

        class UserController
        {
            #[Delete('/api/users/{id}')]
            public function remove(): void {}
        }
        PHP);

        $node = $this->findNode($result['nodes'], 'remove');
        $this->assertNotNull($node);
        $meta = $node->metadata();
        $this->assertSame('/api/users/{id}', $meta['http_path'] ?? null);
        $this->assertSame('DELETE', $meta['http_method'] ?? null);
    }

    public function test_route_attribute_defaults_to_get(): void
    {
        $result = $this->parsePhp(<<<'PHP'
        <?php
        namespace App\Controller;

        class OrderController
        {
            #[Route('/api/orders')]
            public function index(): void {}
        }
        PHP);

        $node = $this->findNode($result['nodes'], 'index');
        $this->assertNotNull($node);
        $meta = $node->metadata();
        $this->assertSame('/api/orders', $meta['http_path'] ?? null);
        $this->assertSame('GET', $meta['http_method'] ?? null);
    }

    public function test_route_attribute_with_explicit_methods(): void
    {
        $result = $this->parsePhp(<<<'PHP'
        <?php
        namespace App\Controller;

        class OrderController
        {
            #[Route('/api/orders', methods: ['GET', 'POST'])]
            public function handle(): void {}
        }
        PHP);

        $node = $this->findNode($result['nodes'], 'handle');
        $this->assertNotNull($node);
        $meta = $node->metadata();
        $this->assertSame('/api/orders', $meta['http_path'] ?? null);
        $this->assertSame('GET,POST', $meta['http_method'] ?? null);
    }

    public function test_no_route_attribute_leaves_no_http_path(): void
    {
        $result = $this->parsePhp(<<<'PHP'
        <?php
        namespace App\Service;

        class UserService
        {
            public function find(int $id): void {}
        }
        PHP);

        $node = $this->findNode($result['nodes'], 'find');
        $this->assertNotNull($node);
        $meta = $node->metadata() ?? [];
        $this->assertArrayNotHasKey('http_path', $meta);
        $this->assertArrayNotHasKey('http_method', $meta);
    }

    // -------------------------------------------------------------------------
    // Class prefix + method route combination
    // -------------------------------------------------------------------------

    public function test_class_route_prefix_combined_with_method_route(): void
    {
        $result = $this->parsePhp(<<<'PHP'
        <?php
        namespace App\Controller;

        #[Route('/api')]
        class UserController
        {
            #[Get('/users')]
            public function list(): void {}
        }
        PHP);

        $node = $this->findNode($result['nodes'], 'list');
        $this->assertNotNull($node);
        $meta = $node->metadata();
        $this->assertSame('/api/users', $meta['http_path'] ?? null);
        $this->assertSame('GET', $meta['http_method'] ?? null);
    }

    public function test_class_prefix_with_route_attribute_on_method(): void
    {
        $result = $this->parsePhp(<<<'PHP'
        <?php
        namespace App\Controller;

        #[Route('/api/v1')]
        class ProductController
        {
            #[Route('/products', methods: ['GET'])]
            public function index(): void {}

            #[Post('/products')]
            public function create(): void {}
        }
        PHP);

        $indexNode  = $this->findNode($result['nodes'], 'index');
        $createNode = $this->findNode($result['nodes'], 'create');

        $this->assertNotNull($indexNode);
        $this->assertNotNull($createNode);

        $this->assertSame('/api/v1/products', $indexNode->metadata()['http_path'] ?? null);
        $this->assertSame('GET',              $indexNode->metadata()['http_method'] ?? null);

        $this->assertSame('/api/v1/products', $createNode->metadata()['http_path'] ?? null);
        $this->assertSame('POST',             $createNode->metadata()['http_method'] ?? null);
    }

    public function test_method_without_route_attr_gets_no_http_path_even_with_class_prefix(): void
    {
        $result = $this->parsePhp(<<<'PHP'
        <?php
        namespace App\Controller;

        #[Route('/api')]
        class UserController
        {
            #[Get('/users')]
            public function list(): void {}

            public function internalHelper(): void {}
        }
        PHP);

        $helper = $this->findNode($result['nodes'], 'internalHelper');
        $this->assertNotNull($helper);
        $meta = $helper->metadata() ?? [];
        $this->assertArrayNotHasKey('http_path', $meta);
    }

    // -------------------------------------------------------------------------
    // Path joining edge cases
    // -------------------------------------------------------------------------

    public function test_trailing_slash_on_base_path_is_normalised(): void
    {
        $result = $this->parsePhp(<<<'PHP'
        <?php
        namespace App\Controller;

        #[Route('/api/')]
        class UserController
        {
            #[Get('/users')]
            public function list(): void {}
        }
        PHP);

        $node = $this->findNode($result['nodes'], 'list');
        $this->assertNotNull($node);
        // Should not produce double slash: /api//users
        $this->assertSame('/api/users', $node->metadata()['http_path'] ?? null);
    }

    public function test_put_and_patch_attributes_recognised(): void
    {
        $result = $this->parsePhp(<<<'PHP'
        <?php
        namespace App\Controller;

        class UserController
        {
            #[Put('/api/users/{id}')]
            public function replace(): void {}

            #[Patch('/api/users/{id}')]
            public function update(): void {}
        }
        PHP);

        $replace = $this->findNode($result['nodes'], 'replace');
        $update  = $this->findNode($result['nodes'], 'update');

        $this->assertSame('PUT',   $replace->metadata()['http_method'] ?? null);
        $this->assertSame('PATCH', $update->metadata()['http_method']  ?? null);
    }
}
