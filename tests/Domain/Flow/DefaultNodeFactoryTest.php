<?php

namespace Tests\Domain\Flow;

use FlowEngine\Domain\Flow\DefaultNodeFactory;
use FlowEngine\Domain\Flow\Node;
use PHPUnit\Framework\TestCase;

final class DefaultNodeFactoryTest extends TestCase
{
    private DefaultNodeFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new DefaultNodeFactory();
    }

    public function test_creates_valid_node(): void
    {
        $node = $this->factory->create(
            class: 'Calculator',
            method: 'sum',
            file: __FILE__,
            line: 10
        );

        $this->assertInstanceOf(Node::class, $node);
        $this->assertEquals('Calculator', $node->class());
        $this->assertEquals('sum', $node->method());
        $this->assertEquals(__FILE__, $node->file());
        $this->assertEquals(10, $node->line());
    }

    public function test_creates_node_with_null_line(): void
    {
        $node = $this->factory->create(
            class: 'Calculator',
            method: 'sum',
            file: __FILE__,
            line: null
        );

        $this->assertNull($node->line());
    }

    public function test_throws_on_empty_class(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot create Node: class name cannot be empty');

        $this->factory->create(
            class: '',
            method: 'sum',
            file: __FILE__,
            line: 10
        );
    }

    public function test_throws_on_whitespace_only_class(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot create Node: class name cannot be empty');

        $this->factory->create(
            class: '   ',
            method: 'sum',
            file: __FILE__,
            line: 10
        );
    }

    public function test_throws_on_empty_method(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot create Node: method name cannot be empty');

        $this->factory->create(
            class: 'Calculator',
            method: '',
            file: __FILE__,
            line: 10
        );
    }

    public function test_throws_on_whitespace_only_method(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot create Node: method name cannot be empty');

        $this->factory->create(
            class: 'Calculator',
            method: '   ',
            file: __FILE__,
            line: 10
        );
    }

    public function test_throws_on_empty_file(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot create Node: file path cannot be empty');

        $this->factory->create(
            class: 'Calculator',
            method: 'sum',
            file: '',
            line: 10
        );
    }

    public function test_throws_on_nonexistent_file(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot create Node: file does not exist');

        $this->factory->create(
            class: 'Calculator',
            method: 'sum',
            file: '/nonexistent/path/file.php',
            line: 10
        );
    }

    public function test_throws_on_negative_line(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot create Node: line number must be positive, got -1');

        $this->factory->create(
            class: 'Calculator',
            method: 'sum',
            file: __FILE__,
            line: -1
        );
    }

    public function test_throws_on_zero_line(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot create Node: line number must be positive, got 0');

        $this->factory->create(
            class: 'Calculator',
            method: 'sum',
            file: __FILE__,
            line: 0
        );
    }

    public function test_accepts_positive_line_numbers(): void
    {
        $node = $this->factory->create(
            class: 'Calculator',
            method: 'sum',
            file: __FILE__,
            line: 1
        );

        $this->assertEquals(1, $node->line());
    }
}