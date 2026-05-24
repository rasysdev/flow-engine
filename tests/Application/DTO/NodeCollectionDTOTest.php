<?php

namespace Tests\Application\DTO;

use FlowEngine\Application\DTO\NodeCollectionDTO;
use FlowEngine\Application\DTO\NodeDTO;
use FlowEngine\Domain\Flow\Node;
use PHPUnit\Framework\TestCase;

final class NodeCollectionDTOTest extends TestCase
{
    public function test_creates_from_nodes(): void
    {
        $nodes = [
            new Node('Calculator', 'sum', __FILE__, 10),
            new Node('Calculator', 'subtract', __FILE__, 20),
        ];

        $collection = NodeCollectionDTO::fromNodes($nodes);

        $this->assertCount(2, $collection);
        $this->assertEquals('Calculator::sum', $collection->first()->id);
    }

    public function test_validates_items_are_node_dtos(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('All items must be instances of NodeDTO');

        new NodeCollectionDTO(['not a DTO']);
    }

    public function test_first_returns_first_dto(): void
    {
        $dto1 = new NodeDTO('Test::a', 'Test', 'a', __FILE__, 1, 'public', true);
        $dto2 = new NodeDTO('Test::b', 'Test', 'b', __FILE__, 2, 'public', true);

        $collection = new NodeCollectionDTO([$dto1, $dto2]);

        $this->assertSame($dto1, $collection->first());
    }

    public function test_last_returns_last_dto(): void
    {
        $dto1 = new NodeDTO('Test::a', 'Test', 'a', __FILE__, 1, 'public', true);
        $dto2 = new NodeDTO('Test::b', 'Test', 'b', __FILE__, 2, 'public', true);

        $collection = new NodeCollectionDTO([$dto1, $dto2]);

        $this->assertSame($dto2, $collection->last());
    }

    public function test_filter_returns_filtered_collection(): void
    {
        $dto1 = new NodeDTO('Test::a', 'Test', 'a', __FILE__, 1, 'public', true);
        $dto2 = new NodeDTO('Test::b', 'Test', 'b', __FILE__, 2, 'hidden', false);

        $collection = new NodeCollectionDTO([$dto1, $dto2]);

        $filtered = $collection->filter(fn(NodeDTO $n) => $n->isPublic);

        $this->assertCount(1, $filtered);
        $this->assertEquals('Test::a', $filtered->first()->id);
    }

    public function test_map_transforms_collection(): void
    {
        $dto1 = new NodeDTO('Test::a', 'Test', 'a', __FILE__, 1, 'public', true);
        $dto2 = new NodeDTO('Test::b', 'Test', 'b', __FILE__, 2, 'public', true);

        $collection = new NodeCollectionDTO([$dto1, $dto2]);

        $ids = $collection->map(fn(NodeDTO $n) => $n->id);

        $this->assertEquals(['Test::a', 'Test::b'], $ids);
    }

    public function test_is_iterable(): void
    {
        $dto1 = new NodeDTO('Test::a', 'Test', 'a', __FILE__, 1, 'public', true);
        $dto2 = new NodeDTO('Test::b', 'Test', 'b', __FILE__, 2, 'public', true);

        $collection = new NodeCollectionDTO([$dto1, $dto2]);

        $ids = [];
        foreach ($collection as $node) {
            $ids[] = $node->id;
        }

        $this->assertEquals(['Test::a', 'Test::b'], $ids);
    }

    public function test_serializes_to_json(): void
    {
        $dto = new NodeDTO('Test::a', 'Test', 'a', __FILE__, 1, 'public', true);
        $collection = new NodeCollectionDTO([$dto]);

        $json = $collection->toJson();
        $decoded = json_decode($json, true);

        $this->assertCount(1, $decoded);
        $this->assertEquals('Test::a', $decoded[0]['id']);
    }
}