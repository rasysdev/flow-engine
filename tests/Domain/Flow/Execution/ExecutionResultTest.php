<?php

namespace Tests\Domain\Execution;

use PHPUnit\Framework\TestCase;
use FlowEngine\Domain\Execution\ExecutionContext;
use FlowEngine\Domain\Execution\ExecutionResult;
use RuntimeException;

final class ExecutionResultTest extends TestCase
{
    /**
     * Garante que uma execução bem-sucedida é representada corretamente.
     */
    public function test_successful_execution(): void
    {
        $context = ExecutionContext::forNode('Calculator::sum');

        $result = ExecutionResult::success(
            context: $context,
            nodeId: 'Calculator::sum',
            inputs: [1, 2],
            output: 3,
            durationMs: 1.2
        );

        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->isFailure());
        $this->assertSame(3, $result->output);
        $this->assertSame($context, $result->context);
    }

    /**
     * Garante que uma falha captura corretamente a exceção.
     */
    public function test_failed_execution(): void
    {
        $context = ExecutionContext::forNode('Calculator::sum');
        $exception = new RuntimeException('Boom');

        $result = ExecutionResult::failure(
            context: $context,
            nodeId: 'Calculator::sum',
            inputs: [1, 2],
            exception: $exception,
            durationMs: 0.4
        );

        $this->assertTrue($result->isFailure());
        $this->assertSame($exception, $result->exception);
    }
}
