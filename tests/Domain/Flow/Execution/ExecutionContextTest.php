<?php

namespace Tests\Domain\Execution;

use PHPUnit\Framework\TestCase;
use FlowEngine\Domain\Execution\ExecutionContext;
use FlowEngine\Domain\Execution\ExecutionOrigin;
use FlowEngine\Domain\Execution\ExecutionMode;

final class ExecutionContextTest extends TestCase
{
    public function test_system_context_is_created(): void
    {
        $ctx = ExecutionContext::system('direct execution');

        $this->assertSame(ExecutionOrigin::SYSTEM, $ctx->origin);
        $this->assertSame(ExecutionMode::AUTOMATIC, $ctx->mode);
        $this->assertSame('direct execution', $ctx->intent);
        $this->assertNotEmpty($ctx->id);
        $this->assertGreaterThan(0, $ctx->startedAt);
    }

    public function test_guided_context_is_created(): void
    {
        $ctx = ExecutionContext::guided('guided run');

        $this->assertSame(ExecutionOrigin::USER, $ctx->origin);
        $this->assertSame(ExecutionMode::GUIDED, $ctx->mode);
        $this->assertSame('guided run', $ctx->intent);
    }
}
