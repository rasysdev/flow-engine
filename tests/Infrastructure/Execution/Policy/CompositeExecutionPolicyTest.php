<?php

namespace Tests\Infrastructure\Execution\Policy;

use PHPUnit\Framework\TestCase;
use FlowEngine\Infrastructure\Execution\Policy\CompositeExecutionPolicy;
use FlowEngine\Infrastructure\Execution\Policy\NodeBlacklistPolicy;
use FlowEngine\Infrastructure\Execution\Policy\RateLimitExecutionPolicy;
use FlowEngine\Domain\Execution\ExecutionContext;
use FlowEngine\Domain\Flow\Node;

/**
 * @covers \FlowEngine\Infrastructure\Execution\Policy\CompositeExecutionPolicy
 */
final class CompositeExecutionPolicyTest extends TestCase
{
    public function test_allows_when_all_policies_allow(): void
    {
        $composite = new CompositeExecutionPolicy([
            new NodeBlacklistPolicy([]),
            new RateLimitExecutionPolicy(maxExecutions: 10, windowSeconds: 60.0),
        ]);

        $node = new Node('Safe', 'process', 'file.php', null);
        $context = ExecutionContext::system('test');

        $result = $composite->canExecute($node, $context);

        $this->assertTrue($result->isAllowed());
        $this->assertSame('All policies passed', $result->reason);
    }

    public function test_denies_on_first_deny_short_circuit(): void
    {
        $composite = new CompositeExecutionPolicy([
            new NodeBlacklistPolicy(['Blocked::method']),
            new RateLimitExecutionPolicy(maxExecutions: 10, windowSeconds: 60.0),
        ]);

        $node = new Node('Blocked', 'method', 'file.php', null);
        $context = ExecutionContext::system('test');

        $result = $composite->canExecute($node, $context);

        $this->assertTrue($result->isDenied());
        $this->assertStringContainsString('blacklisted', $result->reason);
    }

    public function test_empty_policies_allows_all(): void
    {
        $composite = new CompositeExecutionPolicy([]);
        $node = new Node('Any', 'method', 'file.php', null);
        $context = ExecutionContext::system('test');

        $result = $composite->canExecute($node, $context);

        $this->assertTrue($result->isAllowed());
    }
}
