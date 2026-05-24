<?php

namespace Tests\Application\DTO;

use FlowEngine\Application\DTO\NodeDTO;
use FlowEngine\Domain\Flow\Node;
use FlowEngine\Domain\Node\NodeVisibility;
use PHPUnit\Framework\TestCase;

final class NodeDTOTest extends TestCase
{
    public function test_creates_from_node(): void
    {
        $node = new Node('Calculator', 'sum', __FILE__, 10);
        $node = $node->withVisibility(new NodeVisibility(NodeVisibility::PUBLIC));

        $dto = NodeDTO::fromNode($node);

        $this->assertEquals('Calculator::sum', $dto->id);
        $this->assertEquals('Calculator', $dto->class);
        $this->assertEquals('sum', $dto->method);
        $this->assertEquals(__FILE__, $dto->file);
        $this->assertEquals(10, $dto->line);
        $this->assertEquals('public', $dto->visibility);
        $this->assertTrue($dto->isPublic);
        $this->assertEquals('php', $dto->language);
    }

    public function test_is_readonly(): void
    {
        $dto = new NodeDTO(
            id: 'Test::method',
            class: 'Test',
            method: 'method',
            file: __FILE__,
            line: 1,
            visibility: 'public',
            isPublic: true
        );

        // Propriedades readonly não podem ser alteradas
        // Não há como testar isso em runtime sem causar Fatal Error
        // Então verificamos que as propriedades foram setadas corretamente
        $this->assertEquals('Test::method', $dto->id);
        $this->assertEquals('Test', $dto->class);

        // A garantia de readonly vem da declaração da classe
        // e é validada em tempo de compilação pelo PHP
        $reflection = new \ReflectionClass($dto);
        $this->assertTrue($reflection->isReadOnly());
    }

    public function test_serializes_to_array(): void
    {
        $dto = new NodeDTO(
            id: 'Calculator::sum',
            class: 'Calculator',
            method: 'sum',
            file: '/path/file.php',
            line: 10,
            visibility: 'public',
            isPublic: true
        );

        $array = $dto->toArray();

        $this->assertEquals([
            'id' => 'Calculator::sum',
            'language' => 'php',
            'namespace' => '',
            'class' => 'Calculator',
            'method' => 'sum',
            'file' => '/path/file.php',
            'line' => 10,
            'visibility' => 'public',
            'isPublic' => true,
        ], $array);
    }

    public function test_serializes_to_json(): void
    {
        $dto = new NodeDTO(
            id: 'Calculator::sum',
            class: 'Calculator',
            method: 'sum',
            file: '/path/file.php',
            line: 10,
            visibility: 'public',
            isPublic: true
        );

        $json = $dto->toJson();
        $decoded = json_decode($json, true);

        $this->assertEquals('Calculator::sum', $decoded['id']);
        $this->assertEquals('Calculator', $decoded['class']);
    }
}
