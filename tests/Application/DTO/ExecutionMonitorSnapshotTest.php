<?php

namespace Tests\Application\DTO;

use PHPUnit\Framework\TestCase;
use FlowEngine\Application\DTO\ExecutionMonitorSnapshot;
use FlowEngine\Domain\Execution\ExecutionEvent;
use FlowEngine\Domain\Execution\ExecutionContext;

/**
 * @covers \FlowEngine\Application\DTO\ExecutionMonitorSnapshot
 */
final class ExecutionMonitorSnapshotTest extends TestCase
{
    public function test_snapshot_is_readonly(): void
    {
        $snapshot = new ExecutionMonitorSnapshot(
            totalExecutions: 10,
            successCount: 8,
            failureCount: 2,
            averageDurationMs: 15.5,
            successRate: 0.8,
            lastEvents: []
        );

        $this->assertSame(10, $snapshot->totalExecutions);
        $this->assertSame(8, $snapshot->successCount);
        $this->assertSame(2, $snapshot->failureCount);
        $this->assertSame(15.5, $snapshot->averageDurationMs);
        $this->assertSame(0.8, $snapshot->successRate);
        $this->assertEmpty($snapshot->lastEvents);
    }

    public function test_snapshot_to_array(): void
    {
        $event = ExecutionEvent::succeeded(
            nodeId: 'A::x', inputs: [], output: null, durationMs: 1.0,
            context: ExecutionContext::system('test')
        );

        $snapshot = new ExecutionMonitorSnapshot(
            totalExecutions: 5,
            successCount: 4,
            failureCount: 1,
            averageDurationMs: 12.0,
            successRate: 0.8,
            lastEvents: [$event]
        );

        $array = $snapshot->toArray();

        $this->assertSame(5, $array['totalExecutions']);
        $this->assertSame(4, $array['successCount']);
        $this->assertSame(1, $array['failureCount']);
        $this->assertSame(12.0, $array['averageDurationMs']);
        $this->assertSame(0.8, $array['successRate']);
        $this->assertSame(1, $array['lastEventsCount']);
    }
}
