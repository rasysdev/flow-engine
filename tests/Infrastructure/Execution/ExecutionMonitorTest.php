<?php

namespace Tests\Infrastructure\Execution;

use PHPUnit\Framework\TestCase;
use FlowEngine\Infrastructure\Execution\Observability\ExecutionMonitor;
use FlowEngine\Domain\Execution\ExecutionEvent;
use FlowEngine\Domain\Execution\ExecutionContext;

/**
 * @covers \FlowEngine\Infrastructure\Execution\Observability\ExecutionMonitor
 */
final class ExecutionMonitorTest extends TestCase
{
    public function test_snapshot_starts_with_zero_stats(): void
    {
        $monitor = new ExecutionMonitor();
        $snapshot = $monitor->snapshot();

        $this->assertSame(0, $snapshot->totalExecutions);
        $this->assertSame(0, $snapshot->successCount);
        $this->assertSame(0, $snapshot->failureCount);
        $this->assertSame(0.0, $snapshot->averageDurationMs);
        $this->assertSame(0.0, $snapshot->successRate);
        $this->assertEmpty($snapshot->lastEvents);
    }

    public function test_it_counts_successful_execution(): void
    {
        $monitor = new ExecutionMonitor();

        $monitor->notify(ExecutionEvent::succeeded(
            nodeId: 'A::x', inputs: [], output: null, durationMs: 10.0,
            context: ExecutionContext::system('test')
        ));

        $snapshot = $monitor->snapshot();

        $this->assertSame(1, $snapshot->totalExecutions);
        $this->assertSame(1, $snapshot->successCount);
        $this->assertSame(0, $snapshot->failureCount);
    }

    public function test_it_counts_failed_execution(): void
    {
        $monitor = new ExecutionMonitor();

        $monitor->notify(ExecutionEvent::failed(
            nodeId: 'A::x', inputs: [],
            exception: new \RuntimeException('fail'),
            durationMs: 5.0,
            context: ExecutionContext::system('test')
        ));

        $snapshot = $monitor->snapshot();

        $this->assertSame(1, $snapshot->totalExecutions);
        $this->assertSame(0, $snapshot->successCount);
        $this->assertSame(1, $snapshot->failureCount);
    }

    public function test_it_calculates_average_duration(): void
    {
        $monitor = new ExecutionMonitor();

        $monitor->notify(ExecutionEvent::succeeded(
            nodeId: 'A::x', inputs: [], output: null, durationMs: 10.0,
            context: ExecutionContext::system('test')
        ));

        $monitor->notify(ExecutionEvent::succeeded(
            nodeId: 'B::y', inputs: [], output: null, durationMs: 20.0,
            context: ExecutionContext::system('test')
        ));

        $snapshot = $monitor->snapshot();

        $this->assertSame(15.0, $snapshot->averageDurationMs);
    }

    public function test_it_calculates_success_rate(): void
    {
        $monitor = new ExecutionMonitor();

        $monitor->notify(ExecutionEvent::succeeded(
            nodeId: 'A::x', inputs: [], output: null, durationMs: 1.0,
            context: ExecutionContext::system('test')
        ));

        $monitor->notify(ExecutionEvent::succeeded(
            nodeId: 'B::y', inputs: [], output: null, durationMs: 1.0,
            context: ExecutionContext::system('test')
        ));

        $monitor->notify(ExecutionEvent::failed(
            nodeId: 'C::z', inputs: [],
            exception: new \RuntimeException('fail'),
            durationMs: 1.0,
            context: ExecutionContext::system('test')
        ));

        $snapshot = $monitor->snapshot();

        // 2 success / 3 total ≈ 0.6667
        $this->assertEqualsWithDelta(2 / 3, $snapshot->successRate, 0.0001);
    }

    public function test_it_tracks_recent_events(): void
    {
        $monitor = new ExecutionMonitor();

        $monitor->notify(ExecutionEvent::started(
            nodeId: 'A::x', inputs: [],
            context: ExecutionContext::system('test')
        ));

        $monitor->notify(ExecutionEvent::succeeded(
            nodeId: 'A::x', inputs: [], output: null, durationMs: 1.0,
            context: ExecutionContext::system('test')
        ));

        $snapshot = $monitor->snapshot();

        $this->assertCount(2, $snapshot->lastEvents);
    }

    public function test_it_respects_max_recent_events_limit(): void
    {
        $monitor = new ExecutionMonitor(maxRecentEvents: 3);

        for ($i = 0; $i < 5; $i++) {
            $monitor->notify(ExecutionEvent::succeeded(
                nodeId: "N::{$i}", inputs: [], output: null, durationMs: 1.0,
                context: ExecutionContext::system('test')
            ));
        }

        $snapshot = $monitor->snapshot();

        $this->assertCount(3, $snapshot->lastEvents);
        // Should keep the 3 most recent (N::2, N::3, N::4)
        $this->assertSame('N::2', $snapshot->lastEvents[0]->nodeId);
        $this->assertSame('N::4', $snapshot->lastEvents[2]->nodeId);
    }

    public function test_reset_clears_all_stats(): void
    {
        $monitor = new ExecutionMonitor();

        $monitor->notify(ExecutionEvent::succeeded(
            nodeId: 'A::x', inputs: [], output: null, durationMs: 10.0,
            context: ExecutionContext::system('test')
        ));

        $monitor->reset();
        $snapshot = $monitor->snapshot();

        $this->assertSame(0, $snapshot->totalExecutions);
        $this->assertSame(0, $snapshot->successCount);
        $this->assertSame(0, $snapshot->failureCount);
        $this->assertSame(0.0, $snapshot->averageDurationMs);
        $this->assertEmpty($snapshot->lastEvents);
    }

    public function test_started_events_do_not_count_as_executions(): void
    {
        $monitor = new ExecutionMonitor();

        $monitor->notify(ExecutionEvent::started(
            nodeId: 'A::x', inputs: [],
            context: ExecutionContext::system('test')
        ));

        $snapshot = $monitor->snapshot();

        $this->assertSame(0, $snapshot->totalExecutions);
        $this->assertCount(1, $snapshot->lastEvents); // Still tracked in ring buffer
    }
}
