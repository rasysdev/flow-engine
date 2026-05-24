<?php

use PHPUnit\Framework\TestCase;
use FlowEngine\Application\UseCase\CompareSnapshots;
use FlowEngine\Application\UseCase\AnalyzeMetrics;
use FlowEngine\Application\UseCase\AnalyzeComplexity;
use FlowEngine\Application\UseCase\AnalyzeCycles;
use FlowEngine\Application\UseCase\AnalyzeArchitecture;
use FlowEngine\Application\UseCase\AnalyzeOrphans;
use FlowEngine\Application\DTO\SnapshotComparisonDTO;
use FlowEngine\Infrastructure\Cache\SnapshotStore;
use FlowEngine\Domain\Flow\Node;
use Tests\Support\InMemoryFlowRepository;
use Tests\Support\TestProjectContext;

final class CompareSnapshotsTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/flow-engine-test-' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function test_compare_returns_snapshot_comparison_dto(): void
    {
        $repo = new InMemoryFlowRepository([
            new Node('App\\Service', 'handle', __FILE__, 1),
        ]);

        $context = new TestProjectContext($this->tmpDir);
        $store = new SnapshotStore($context);

        // Save a baseline snapshot manually
        $baselineData = [
            'metrics' => [
                'totalNodes' => 5,
                'totalEdges' => 3,
                'avgFanIn' => 0.6,
                'avgFanOut' => 0.6,
                'maxFanIn' => 2,
                'maxFanOut' => 2,
                'hotspotCount' => 0,
                'hotspots' => [],
                'topCoupled' => [],
            ],
            'complexity' => [
                'totalMethods' => 5,
                'avgComplexity' => 2.0,
                'maxComplexity' => 5,
                'minComplexity' => 1,
                'byLevel' => ['LOW' => 5, 'MEDIUM' => 0, 'HIGH' => 0, 'CRITICAL' => 0],
                'complexMethods' => [],
            ],
            'cycles' => [
                'totalCycles' => 0,
                'totalNodesInCycles' => 0,
                'bySeverity' => ['LOW' => 0, 'MEDIUM' => 0, 'HIGH' => 0, 'CRITICAL' => 0],
                'largestCycle' => 0,
                'cycles' => [],
            ],
            'architecture' => [
                'isClean' => true,
                'totalViolations' => 0,
                'bySeverity' => ['CRITICAL' => 0, 'HIGH' => 0],
                'byType' => [],
                'layerDistribution' => [],
                'violations' => [],
            ],
            'orphans' => [
                'totalOrphans' => 0,
                'highConfidenceOrphans' => 0,
                'suspiciousLeafNodes' => 0,
                'percentageOrphans' => 0,
                'orphans' => [],
                'suspiciousLeaves' => [],
            ],
        ];

        $store->save('baseline', $baselineData);

        $useCase = new CompareSnapshots(
            new AnalyzeMetrics($repo),
            new AnalyzeComplexity($repo),
            new AnalyzeCycles($repo),
            new AnalyzeArchitecture($repo),
            new AnalyzeOrphans($repo),
            $store
        );

        $result = $useCase->execute('baseline');

        $this->assertInstanceOf(SnapshotComparisonDTO::class, $result);
        $this->assertSame('baseline', $result->baselineLabel);
        $this->assertSame('current', $result->currentLabel);
        $this->assertArrayHasKey('totalNodes', $result->metrics);
        $this->assertArrayHasKey('summary', $result->toArray());
    }

    public function test_result_serializes_to_json(): void
    {
        $repo = new InMemoryFlowRepository([
            new Node('App\\Service', 'handle', __FILE__, 1),
        ]);

        $context = new TestProjectContext($this->tmpDir);
        $store = new SnapshotStore($context);

        $baselineData = [
            'metrics' => [
                'totalNodes' => 1,
                'totalEdges' => 0,
                'avgFanIn' => 0,
                'avgFanOut' => 0,
                'maxFanIn' => 0,
                'maxFanOut' => 0,
                'hotspotCount' => 0,
            ],
            'cycles' => ['totalCycles' => 0, 'cycles' => []],
            'architecture' => ['totalViolations' => 0, 'violations' => []],
            'orphans' => ['totalOrphans' => 0, 'orphans' => []],
            'complexity' => ['avgComplexity' => 0, 'complexMethods' => []],
        ];

        $store->save('baseline', $baselineData);

        $useCase = new CompareSnapshots(
            new AnalyzeMetrics($repo),
            new AnalyzeComplexity($repo),
            new AnalyzeCycles($repo),
            new AnalyzeArchitecture($repo),
            new AnalyzeOrphans($repo),
            $store
        );

        $result = $useCase->execute('baseline');
        $json = $result->toJson();

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertSame('baseline', $decoded['baselineLabel']);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getRealPath());
            } else {
                unlink($item->getRealPath());
            }
        }

        rmdir($dir);
    }
}
