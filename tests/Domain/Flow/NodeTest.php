<?php

namespace Tests\Domain\Flow;

use FlowEngine\Domain\Flow\Node;
use FlowEngine\Domain\Node\NodeVisibility;
use PHPUnit\Framework\TestCase;

final class NodeTest extends TestCase
{
    public function test_node_is_created_with_basic_properties(): void
    {
        $node = new Node(
            class: 'Calculator',
            method: 'sum',
            file: __FILE__,
            line: 10
        );

        $this->assertEquals('Calculator::sum', $node->id());
        $this->assertEquals('Calculator', $node->class());
        $this->assertEquals('sum', $node->method());
        $this->assertEquals(__FILE__, $node->file());
        $this->assertEquals(10, $node->line());
    }

    public function test_node_has_default_public_visibility(): void
    {
        $node = new Node(
            class: 'Calculator',
            method: 'sum',
            file: __FILE__,
            line: 10
        );

        $this->assertTrue($node->isPublic());
        $this->assertEquals(NodeVisibility::PUBLIC , $node->visibility()->value());
    }

    public function test_node_is_not_evaluated_by_default(): void
    {
        $node = new Node(
            class: 'Calculator',
            method: 'sum',
            file: __FILE__,
            line: 10
        );

        $this->assertFalse($node->hasEvaluatedVisibility());
    }

    public function test_with_visibility_returns_new_instance(): void
    {
        $node = new Node(
            class: 'Calculator',
            method: 'sum',
            file: __FILE__,
            line: 10
        );

        $updated = $node->withVisibility(
            new NodeVisibility(NodeVisibility::HIDDEN)  // ← CORRIGIDO: HIDDEN ao invés de INTERNAL
        );

        // Original não muda
        $this->assertTrue($node->isPublic());
        $this->assertFalse($node->hasEvaluatedVisibility());

        // Nova instância tem nova visibilidade
        $this->assertFalse($updated->isPublic());  // ← HIDDEN não é público
        $this->assertTrue($updated->hasEvaluatedVisibility());

        // São instâncias diferentes
        $this->assertNotSame($node, $updated);
    }

    public function test_with_visibility_marks_as_evaluated(): void
    {
        $node = new Node(
            class: 'Calculator',
            method: 'sum',
            file: __FILE__,
            line: 10
        );

        $evaluated = $node->withVisibility(
            new NodeVisibility(NodeVisibility::PUBLIC)
        );

        $this->assertTrue($evaluated->hasEvaluatedVisibility());
    }

    public function test_validate_throws_on_empty_class(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Node class cannot be empty');

        $node = new Node(
            class: '',
            method: 'sum',
            file: __FILE__,
            line: 10
        );

        $node->validate();
    }

    public function test_validate_throws_on_empty_method(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Node method cannot be empty');

        $node = new Node(
            class: 'Calculator',
            method: '',
            file: __FILE__,
            line: 10
        );

        $node->validate();
    }

    public function test_validate_throws_on_nonexistent_file(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Node file does not exist');

        $node = new Node(
            class: 'Calculator',
            method: 'sum',
            file: '/nonexistent/file.php',
            line: 10
        );

        $node->validate();
    }

    public function test_validate_passes_on_valid_node(): void
    {
        $node = new Node(
            class: 'Calculator',
            method: 'sum',
            file: __FILE__,
            line: 10
        );

        // Não deve lançar exceção
        $node->validate();

        $this->assertTrue(true);
    }
}