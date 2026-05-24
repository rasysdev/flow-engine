<?php

namespace Tests\Application\UseCase;

use FlowEngine\Application\UseCase\GetNodeInputs;
use FlowEngine\Domain\Contracts\InputIntrospector;
use FlowEngine\Domain\Flow\Node;
use PHPUnit\Framework\TestCase;

final class GetNodeInputsTest extends TestCase
{
    public function test_it_returns_inputs_and_return_type(): void
    {
        $node = new Node(
            'Calculator',
            'sum',
            'Calculator.php',
            10
        );


        $introspector = $this->createMock(InputIntrospector::class);

        $introspector
            ->expects($this->once())
            ->method('introspect')
            ->with($node)
            ->willReturn([
                'inputs' => [
                    [
                        'name' => 'a',
                        'type' => 'int',
                        'required' => true,
                        'default' => null,
                    ],
                    [
                        'name' => 'b',
                        'type' => 'int',
                        'required' => true,
                        'default' => null,
                    ],
                ],
                'return' => 'int'
            ]);

        $useCase = new GetNodeInputs($introspector);

        $result = $useCase->execute($node);

        // Assert DTO, não array
        $this->assertSame('int', $result->returnType);
        $this->assertCount(2, $result->inputs);

        $this->assertSame('a', $result->inputs[0]->name);
        $this->assertSame('int', $result->inputs[0]->type);
        $this->assertTrue($result->inputs[0]->required);
        $this->assertNull($result->inputs[0]->default);

        $this->assertSame('b', $result->inputs[1]->name);
        $this->assertSame('int', $result->inputs[1]->type);
        $this->assertTrue($result->inputs[1]->required);
        $this->assertNull($result->inputs[1]->default);
    }
}
