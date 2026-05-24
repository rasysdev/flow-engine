<?php

namespace Tests\Infrastructure\Analyzer\Visitors;

use FlowEngine\Domain\Flow\DefaultNodeFactory;
use FlowEngine\Infrastructure\Analyzer\AstParser;
use PHPUnit\Framework\TestCase;

final class ModelPropertyVisitorTest extends TestCase
{
    public function test_it_extracts_model_properties(): void
    {
        $root = realpath(__DIR__ . '/../../Fixtures/ExampleProject');
        self::assertNotFalse($root);

        $file = $root . '/App/Models/CollectionRun.php';
        self::assertFileExists($file);

        $parser = new AstParser(new DefaultNodeFactory());
        $result = $parser->parse($file);

        // Find the __model node
        $modelNode = null;
        foreach ($result['nodes'] as $node) {
            if ($node->method() === '__model') {
                $modelNode = $node;
                break;
            }
        }

        self::assertNotNull($modelNode, '__model node not found');

        $meta = $modelNode->metadata();
        self::assertNotNull($meta);

        // Table
        self::assertSame('collection_runs', $meta['table']);

        // Fillable
        self::assertContains('client_id', $meta['fillable']);
        self::assertContains('client_name', $meta['fillable']);
        self::assertContains('account_id', $meta['fillable']);
        self::assertContains('status', $meta['fillable']);
        self::assertCount(4, $meta['fillable']);

        // Casts
        self::assertArrayHasKey('collected_at', $meta['casts']);
        self::assertSame('datetime', $meta['casts']['collected_at']);
        self::assertArrayHasKey('checks', $meta['casts']);
        self::assertSame('array', $meta['casts']['checks']);

        // Relationships
        self::assertCount(2, $meta['relationships']);
        $relTypes = array_column($meta['relationships'], 'type');
        self::assertContains('belongsTo', $relTypes);
        self::assertContains('hasMany', $relTypes);
    }

    public function test_it_creates_relationship_edges(): void
    {
        $root = realpath(__DIR__ . '/../../Fixtures/ExampleProject');
        self::assertNotFalse($root);

        $file = $root . '/App/Models/CollectionRun.php';
        $parser = new AstParser(new DefaultNodeFactory());
        $result = $parser->parse($file);

        $modelEdges = array_filter(
            $result['edges'],
            fn($e) => $e->type() === 'model_relationship'
        );

        self::assertNotEmpty($modelEdges);

        $targets = array_map(fn($e) => $e->to(), $modelEdges);
        self::assertContains('App\\Models\\Client::__model', $targets);
        self::assertContains('App\\Models\\CollectionResult::__model', $targets);
    }

    public function test_it_extracts_method_signatures(): void
    {
        $root = realpath(__DIR__ . '/../../Fixtures/ExampleProject');
        self::assertNotFalse($root);

        $file = $root . '/App/src/Calculator.php';
        self::assertFileExists($file);

        $parser = new AstParser(new DefaultNodeFactory());
        $result = $parser->parse($file);

        $nodeMap = [];
        foreach ($result['nodes'] as $node) {
            $nodeMap[$node->method()] = $node;
        }

        // sum(int $a, int $b): int
        self::assertArrayHasKey('sum', $nodeMap);
        $sumMeta = $nodeMap['sum']->metadata();
        self::assertNotNull($sumMeta);
        self::assertSame('int', $sumMeta['returnType']);
        self::assertCount(2, $sumMeta['params']);
        self::assertSame('$a', $sumMeta['params'][0]['name']);
        self::assertSame('int', $sumMeta['params'][0]['type']);

        // divide(float $a, float $b): float
        self::assertArrayHasKey('divide', $nodeMap);
        $divMeta = $nodeMap['divide']->metadata();
        self::assertNotNull($divMeta);
        self::assertSame('float', $divMeta['returnType']);
        self::assertSame('float', $divMeta['params'][0]['type']);

        // power(int $base, int $exponent = 2): int
        self::assertArrayHasKey('power', $nodeMap);
        $powMeta = $nodeMap['power']->metadata();
        self::assertNotNull($powMeta);
        self::assertSame('int', $powMeta['returnType']);
        self::assertCount(2, $powMeta['params']);

        // privateHelper(): string (should still have signature even if private)
        self::assertArrayHasKey('privateHelper', $nodeMap);
        $helperMeta = $nodeMap['privateHelper']->metadata();
        self::assertNotNull($helperMeta);
        self::assertSame('string', $helperMeta['returnType']);
    }
}
