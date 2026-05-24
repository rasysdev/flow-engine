<?php

namespace Tests\Infrastructure\Execution\Policy;

use PHPUnit\Framework\TestCase;
use FlowEngine\Infrastructure\Execution\Policy\RateLimitExecutionPolicy;
use FlowEngine\Domain\Execution\ExecutionContext;
use FlowEngine\Domain\Flow\Node;

/**
 * @covers \FlowEngine\Infrastructure\Execution\Policy\RateLimitExecutionPolicy
 */
final class RateLimitExecutionPolicyTest extends TestCase
{
    public function test_allows_execution_within_limit(): void
    {
        $policy = new RateLimitExecutionPolicy(maxExecutions: 3, windowSeconds: 60.0);
        $node = new Node('A', 'x', 'file.php', null);
        $context = ExecutionContext::system('test');

        $result = $policy->canExecute($node, $context);

        $this->assertTrue($result->isAllowed());
    }

    public function test_denies_execution_when_limit_exceeded(): void
    {
        $policy = new RateLimitExecutionPolicy(maxExecutions: 2, windowSeconds: 60.0);
        $node = new Node('A', 'x', 'file.php', null);
        $context = ExecutionContext::system('test');

        $policy->canExecute($node, $context); // 1
        $policy->canExecute($node, $context); // 2

        $result = $policy->canExecute($node, $context); // 3 → denied

        $this->assertTrue($result->isDenied());
        $this->assertStringContainsString('Rate limit exceeded', $result->reason);
    }

    public function test_different_nodes_have_independent_limits(): void
    {
        $policy = new RateLimitExecutionPolicy(maxExecutions: 1, windowSeconds: 60.0);
        $nodeA = new Node('A', 'x', 'file.php', null);
        $nodeB = new Node('B', 'y', 'file.php', null);
        $context = ExecutionContext::system('test');

        $policy->canExecute($nodeA, $context); // A: 1 → ok

        $result = $policy->canExecute($nodeB, $context); // B: 1 → ok (independent)

        $this->assertTrue($result->isAllowed());
    }

    public function test_allows_execution_after_window_expires(): void
    {
        // Very short window
        $policy = new RateLimitExecutionPolicy(maxExecutions: 1, windowSeconds: 0.02);
        $node = new Node('A', 'x', 'file.php', null);
        $context = ExecutionContext::system('test');

        $policy->canExecute($node, $context); // 1 → ok

        usleep(50000); // 50ms > 20ms window

        $result = $policy->canExecute($node, $context); // window expired → ok

        $this->assertTrue($result->isAllowed());
    }

    public function test_tracks_exact_count_in_window(): void
    {
        $policy = new RateLimitExecutionPolicy(maxExecutions: 3, windowSeconds: 60.0);
        $node = new Node('A', 'x', 'file.php', null);
        $context = ExecutionContext::system('test');

        $this->assertTrue($policy->canExecute($node, $context)->isAllowed()); // 1
        $this->assertTrue($policy->canExecute($node, $context)->isAllowed()); // 2
        $this->assertTrue($policy->canExecute($node, $context)->isAllowed()); // 3
        $this->assertTrue($policy->canExecute($node, $context)->isDenied()); // 4 → denied
    }
}
