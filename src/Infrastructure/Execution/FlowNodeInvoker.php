<?php

namespace FlowEngine\Infrastructure\Execution;

use FlowEngine\Domain\Contracts\FlowRepository;
use FlowEngine\Domain\Execution\{
    ExecutionContext,
    ExecutionDeniedException,
    ExecutionEvent,
    ExecutionEventType,
    ExecutionObserver,
    ExecutionPolicy,
    ExecutionResult
};
use FlowEngine\Domain\Flow\Node;
use FlowEngine\Domain\Contracts\NodeExecutor;
use FlowEngine\Domain\Flow\ContextualNodeInvoker;
use Throwable;

final class FlowNodeInvoker implements ContextualNodeInvoker
{
    /** @var ExecutionObserver[] */
    private array $observers;

    /** @var ExecutionPolicy[] */
    private array $policies;

    /**
     * @param FlowRepository     $repository Repositório de flows
     * @param NodeExecutor       $executor   Executor de nós
     * @param ExecutionObserver[] $observers  Observadores de execução
     * @param ExecutionPolicy[]   $policies   Políticas de execução
     */
    public function __construct(
        private FlowRepository $repository,
        private NodeExecutor $executor,
        array $observers = [],
        array $policies = []
    ) {
        $this->observers = $observers;
        $this->policies = $policies;
    }

    public function invoke(Node $node, array $inputs): ExecutionResult
    {
        return $this->invokeWithContext(
            $node,
            $inputs,
            ExecutionContext::system('direct execution')
        );
    }

    public function invokeWithContext(Node $node, array $inputs, ExecutionContext $context): ExecutionResult
    {
        $start = microtime(true);

        // Avaliar policies antes de executar
        foreach ($this->policies as $policy) {
            $policyResult = $policy->canExecute($node, $context);

            if ($policyResult->isDenied()) {
                $exception = new ExecutionDeniedException($node->id(), $policyResult);

                $this->notify(
                    ExecutionEvent::failed(
                        nodeId: $node->id(),
                        inputs: $inputs,
                        exception: $exception,
                        durationMs: (microtime(true) - $start) * 1000,
                        context: $context
                    )
                );

                return ExecutionResult::failure(
                    context: $context,
                    nodeId: $node->id(),
                    inputs: $inputs,
                    exception: $exception,
                    durationMs: (microtime(true) - $start) * 1000
                );
            }
        }

        $this->notify(
            ExecutionEvent::started(
                nodeId: $node->id(),
                inputs: $inputs,
                context: $context
            )
        );

        try {
            $output = $this->executor->execute($node, $inputs);

            $result = ExecutionResult::success(
                context: $context,
                nodeId: $node->id(),
                inputs: $inputs,
                output: $output,
                durationMs: (microtime(true) - $start) * 1000
            );

            $this->notify(
                ExecutionEvent::succeeded(
                    nodeId: $node->id(),
                    inputs: $inputs,
                    output: $output,
                    durationMs: (microtime(true) - $start) * 1000,
                    context: $context
                )
            );

            return $result;
        } catch (Throwable $e) {
            $result = ExecutionResult::failure(
                context: $context,
                nodeId: $node->id(),
                inputs: $inputs,
                exception: $e,
                durationMs: (microtime(true) - $start) * 1000
            );

            $this->notify(
                ExecutionEvent::failed(
                    nodeId: $node->id(),
                    inputs: $inputs,
                    exception: $e,
                    durationMs: (microtime(true) - $start) * 1000,
                    context: $context
                )
            );

            return $result;
        }
    }

    private function notify(ExecutionEvent $event): void
    {
        foreach ($this->observers as $observer) {
            $observer->notify($event);
        }
    }
}
