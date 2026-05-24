<?php

namespace Tests\Infrastructure\Execution;

use FlowEngine\Domain\Execution\ExecutionContext;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use FlowEngine\Infrastructure\Execution\Observability\ExecutionEventLogger;
use FlowEngine\Domain\Execution\ExecutionEvent;
use FlowEngine\Domain\Execution\ExecutionEventType;

/**
 * @covers \FlowEngine\Infrastructure\Execution\Observability\ExecutionEventLogger
 *
 * Testa o adapter de observabilidade responsável por
 * transformar ExecutionEvents em logs estruturados.
 */
final class ExecutionEventLoggerTest extends TestCase
{
    /**
     * Garante que um evento de execução bem-sucedida
     * é logado corretamente via PSR-3 LoggerInterface.
     */
    public function test_it_logs_execution_succeeded_event(): void
    {
        // Arrange
        $logger = $this->createMock(LoggerInterface::class);

        $logger
            ->expects($this->once())
            ->method('info')
            ->with(
                ExecutionEventType::SUCCEEDED->value,
                $this->callback(function (array $context) {
                    return isset($context['nodeId'])
                        && $context['nodeId'] === 'Calculator::sum'
                        && isset($context['durationMs']);
                })
            );

        $observer = new ExecutionEventLogger($logger);

        $event = ExecutionEvent::succeeded(
            nodeId: 'Calculator::sum',
            inputs: [],
            output: null,
            durationMs: 1.2,
            context: ExecutionContext::system('test')
        );

        // Act
        $observer->notify($event);
    }

    /**
     * Garante que um evento de falha na execução
     * também é logado com payload estruturado.
     */
    public function test_it_logs_execution_failed_event(): void
    {
        // Arrange
        $logger = $this->createMock(LoggerInterface::class);

        $logger
            ->expects($this->once())
            ->method('info')
            ->with(
                ExecutionEventType::FAILED->value,
                $this->callback(function (array $context) {
                    return isset($context['nodeId'])
                        && $context['nodeId'] === 'Calculator::explode'
                        && isset($context['error']);
                })
            );

        $observer = new ExecutionEventLogger($logger);

        $event = ExecutionEvent::failed(
            nodeId: 'Calculator::explode',
            inputs: [],
            exception: new \RuntimeException('Boom'),
            durationMs: 0.42,
            context: ExecutionContext::system('test')
        );

        // Act
        $observer->notify($event);
    }
}
