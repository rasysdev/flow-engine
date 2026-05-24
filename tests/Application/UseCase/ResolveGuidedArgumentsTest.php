<?php

namespace Tests\Application\UseCase;

use FlowEngine\Application\UseCase\ResolveGuidedArguments;
use FlowEngine\Application\DTO\NodeInputDefinition;
use FlowEngine\Domain\Flow\Node;
use PHPUnit\Framework\TestCase;
use LogicException;

final class ResolveGuidedArgumentsTest extends TestCase
{
    public function test_it_resolves_arguments_with_cast_and_defaults(): void
    {
        // Arrange
        $node = new Node(
            'Calculator',
            'sum',
            'Calculator.php',
            10
        );

        $inputs = [
            new NodeInputDefinition(
                name: 'a',
                type: 'int',
                required: true,
                default: null
            ),
            new NodeInputDefinition(
                name: 'b',
                type: 'int',
                required: false,
                default: 10
            ),
        ];

        $rawArgs = ['5'];

        $useCase = new ResolveGuidedArguments();

        // Act
        $result = $useCase->execute($node, $inputs, $rawArgs);

        // Assert
        $this->assertSame([5, 10], $result->args);

        $this->assertCount(2, $result->inputs);
        $this->assertSame('a', $result->inputs[0]->name);
        $this->assertSame(5, $result->inputs[0]->value);
        $this->assertSame('b', $result->inputs[1]->name);
        $this->assertSame(10, $result->inputs[1]->value);
    }

    public function test_it_fails_when_required_argument_is_missing(): void
    {
        // Arrange
        $node = new Node(
            'Calculator',
            'sum',
            'Calculator.php',
            10
        );

        $inputs = [
            new NodeInputDefinition(
                name: 'a',
                type: 'int',
                required: true,
                default: null
            ),
        ];

        $rawArgs = [];

        $useCase = new ResolveGuidedArguments();

        // Assert
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Missing argument: a');

        // Act
        $useCase->execute($node, $inputs, $rawArgs);
    }
}
