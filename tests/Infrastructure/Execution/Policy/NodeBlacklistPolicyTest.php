<?php

namespace Tests\Infrastructure\Execution\Policy;

use PHPUnit\Framework\TestCase;
use FlowEngine\Infrastructure\Execution\Policy\NodeBlacklistPolicy;
use FlowEngine\Domain\Execution\ExecutionContext;
use FlowEngine\Domain\Flow\Node;

/**
 * @covers \FlowEngine\Infrastructure\Execution\Policy\NodeBlacklistPolicy
 */
final class NodeBlacklistPolicyTest extends TestCase
{
    public function test_denies_blacklisted_node(): void
    {
        $policy = new NodeBlacklistPolicy(['Dangerous::delete', 'Admin::dropTable']);
        $node = new Node('Dangerous', 'delete', 'file.php', null);
        $context = ExecutionContext::system('test');

        $result = $policy->canExecute($node, $context);

        $this->assertTrue($result->isDenied());
        $this->assertStringContainsString('blacklisted', $result->reason);
    }

    public function test_allows_non_blacklisted_node(): void
    {
        $policy = new NodeBlacklistPolicy(['Dangerous::delete']);
        $node = new Node('Safe', 'process', 'file.php', null);
        $context = ExecutionContext::system('test');

        $result = $policy->canExecute($node, $context);

        $this->assertTrue($result->isAllowed());
    }

    public function test_empty_blacklist_allows_all(): void
    {
        $policy = new NodeBlacklistPolicy([]);
        $node = new Node('Any', 'method', 'file.php', null);
        $context = ExecutionContext::system('test');

        $result = $policy->canExecute($node, $context);

        $this->assertTrue($result->isAllowed());
    }
}
