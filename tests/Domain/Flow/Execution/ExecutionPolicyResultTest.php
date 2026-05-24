<?php

namespace Tests\Domain\Flow\Execution;

use PHPUnit\Framework\TestCase;
use FlowEngine\Domain\Execution\ExecutionPolicyResult;

/**
 * @covers \FlowEngine\Domain\Execution\ExecutionPolicyResult
 */
final class ExecutionPolicyResultTest extends TestCase
{
    public function test_allow_creates_allowed_result(): void
    {
        $result = ExecutionPolicyResult::allow('No restrictions');

        $this->assertTrue($result->isAllowed());
        $this->assertFalse($result->isDenied());
        $this->assertSame('No restrictions', $result->reason);
    }

    public function test_deny_creates_denied_result(): void
    {
        $result = ExecutionPolicyResult::deny('Rate limit exceeded');

        $this->assertFalse($result->isAllowed());
        $this->assertTrue($result->isDenied());
        $this->assertSame('Rate limit exceeded', $result->reason);
    }

    public function test_allow_has_default_reason(): void
    {
        $result = ExecutionPolicyResult::allow();

        $this->assertTrue($result->isAllowed());
        $this->assertSame('Allowed by policy', $result->reason);
    }
}
