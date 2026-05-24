<?php

use PHPUnit\Framework\TestCase;
use FlowEngine\Domain\Analysis\SnapshotComparator;

final class SnapshotComparatorTest extends TestCase
{
    private SnapshotComparator $comparator;

    protected function setUp(): void
    {
        $this->comparator = new SnapshotComparator();
    }

    public function test_compare_empty_snapshots(): void
    {
        $result = $this->comparator->compare([], []);

        $this->assertArrayHasKey('metrics', $result);
        $this->assertArrayHasKey('cycles', $result);
        $this->assertArrayHasKey('violations', $result);
        $this->assertArrayHasKey('orphans', $result);
        $this->assertArrayHasKey('complexity', $result);
        $this->assertArrayHasKey('summary', $result);
    }

    public function test_metrics_delta_calculation(): void
    {
        $baseline = [
            'metrics' => [
                'totalNodes' => 10,
                'totalEdges' => 15,
                'avgFanIn' => 1.5,
                'avgFanOut' => 1.5,
                'maxFanIn' => 5,
                'maxFanOut' => 4,
                'hotspotCount' => 2,
            ],
        ];

        $current = [
            'metrics' => [
                'totalNodes' => 12,
                'totalEdges' => 18,
                'avgFanIn' => 1.8,
                'avgFanOut' => 1.6,
                'maxFanIn' => 6,
                'maxFanOut' => 4,
                'hotspotCount' => 3,
            ],
        ];

        $result = $this->comparator->compare($baseline, $current);

        $this->assertSame([10, 12, 2], $result['metrics']['totalNodes']);
        $this->assertSame([15, 18, 3], $result['metrics']['totalEdges']);
        $this->assertSame([5, 6, 1], $result['metrics']['maxFanIn']);
        $this->assertSame([4, 4, 0], $result['metrics']['maxFanOut']);
    }

    public function test_new_cycles_detected(): void
    {
        $baseline = [
            'cycles' => [
                'totalCycles' => 1,
                'cycles' => [
                    ['nodes' => ['A::call', 'B::call'], 'size' => 2, 'severity' => 'LOW'],
                ],
            ],
        ];

        $current = [
            'cycles' => [
                'totalCycles' => 2,
                'cycles' => [
                    ['nodes' => ['A::call', 'B::call'], 'size' => 2, 'severity' => 'LOW'],
                    ['nodes' => ['C::call', 'D::call'], 'size' => 2, 'severity' => 'LOW'],
                ],
            ],
        ];

        $result = $this->comparator->compare($baseline, $current);

        $this->assertSame(1, $result['cycles']['totalDelta']);
        $this->assertCount(1, $result['cycles']['new']);
        $this->assertEmpty($result['cycles']['resolved']);
    }

    public function test_resolved_cycles_detected(): void
    {
        $baseline = [
            'cycles' => [
                'totalCycles' => 2,
                'cycles' => [
                    ['nodes' => ['A::call', 'B::call'], 'size' => 2, 'severity' => 'LOW'],
                    ['nodes' => ['C::call', 'D::call'], 'size' => 2, 'severity' => 'LOW'],
                ],
            ],
        ];

        $current = [
            'cycles' => [
                'totalCycles' => 1,
                'cycles' => [
                    ['nodes' => ['A::call', 'B::call'], 'size' => 2, 'severity' => 'LOW'],
                ],
            ],
        ];

        $result = $this->comparator->compare($baseline, $current);

        $this->assertSame(-1, $result['cycles']['totalDelta']);
        $this->assertEmpty($result['cycles']['new']);
        $this->assertCount(1, $result['cycles']['resolved']);
    }

    public function test_violation_comparison(): void
    {
        $baseline = [
            'architecture' => [
                'totalViolations' => 1,
                'violations' => [
                    ['from' => 'Domain\\A::call', 'to' => 'Infra\\B::call'],
                ],
            ],
        ];

        $current = [
            'architecture' => [
                'totalViolations' => 2,
                'violations' => [
                    ['from' => 'Domain\\A::call', 'to' => 'Infra\\B::call'],
                    ['from' => 'Domain\\C::call', 'to' => 'Infra\\D::call'],
                ],
            ],
        ];

        $result = $this->comparator->compare($baseline, $current);

        $this->assertSame(1, $result['violations']['totalDelta']);
        $this->assertCount(1, $result['violations']['new']);
        $this->assertEmpty($result['violations']['resolved']);
    }

    public function test_orphan_comparison(): void
    {
        $baseline = [
            'orphans' => [
                'totalOrphans' => 2,
                'orphans' => [
                    ['nodeId' => 'App\\A::unused'],
                    ['nodeId' => 'App\\B::unused'],
                ],
            ],
        ];

        $current = [
            'orphans' => [
                'totalOrphans' => 1,
                'orphans' => [
                    ['nodeId' => 'App\\A::unused'],
                ],
            ],
        ];

        $result = $this->comparator->compare($baseline, $current);

        $this->assertSame(-1, $result['orphans']['totalDelta']);
        $this->assertCount(1, $result['orphans']['resolved']);
        $this->assertEmpty($result['orphans']['new']);
    }

    public function test_complexity_improvements_and_degradations(): void
    {
        $baseline = [
            'complexity' => [
                'avgComplexity' => 15.0,
                'complexMethods' => [
                    ['nodeId' => 'App\\A::handle', 'complexity' => 25],
                    ['nodeId' => 'App\\B::process', 'complexity' => 30],
                ],
            ],
        ];

        $current = [
            'complexity' => [
                'avgComplexity' => 12.0,
                'complexMethods' => [
                    ['nodeId' => 'App\\A::handle', 'complexity' => 15],
                    ['nodeId' => 'App\\B::process', 'complexity' => 35],
                ],
            ],
        ];

        $result = $this->comparator->compare($baseline, $current);

        $this->assertCount(1, $result['complexity']['improved']);
        $this->assertCount(1, $result['complexity']['degraded']);
        $this->assertSame(-3.0, $result['complexity']['avgDelta']);
    }

    public function test_summary_counts(): void
    {
        $baseline = [
            'metrics' => [
                'totalNodes' => 10,
                'totalEdges' => 15,
                'avgFanIn' => 1.5,
                'avgFanOut' => 1.5,
                'maxFanIn' => 5,
                'maxFanOut' => 4,
                'hotspotCount' => 2,
            ],
            'cycles' => ['totalCycles' => 2, 'cycles' => []],
            'architecture' => ['totalViolations' => 1, 'violations' => []],
            'orphans' => ['totalOrphans' => 3, 'orphans' => []],
        ];

        $current = [
            'metrics' => [
                'totalNodes' => 10,
                'totalEdges' => 15,
                'avgFanIn' => 1.5,
                'avgFanOut' => 1.5,
                'maxFanIn' => 5,
                'maxFanOut' => 4,
                'hotspotCount' => 2,
            ],
            'cycles' => ['totalCycles' => 1, 'cycles' => []],
            'architecture' => ['totalViolations' => 1, 'violations' => []],
            'orphans' => ['totalOrphans' => 3, 'orphans' => []],
        ];

        $result = $this->comparator->compare($baseline, $current);

        $this->assertArrayHasKey('improved', $result['summary']);
        $this->assertArrayHasKey('degraded', $result['summary']);
        $this->assertArrayHasKey('unchanged', $result['summary']);
    }
}
