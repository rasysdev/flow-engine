<?php

namespace Tests\Application\UseCase;

use FlowEngine\Application\UseCase\AnalyzeArchitecture;
use FlowEngine\Application\UseCase\PredictViolations;
use FlowEngine\Domain\Flow\Edge;
use FlowEngine\Domain\Flow\Node;
use FlowEngine\Infrastructure\Cache\SnapshotStore;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryFlowRepository;
use Tests\Support\TestProjectContext;

final class PredictViolationsTest extends TestCase
{
    private string $tempDir;
    private SnapshotStore $store;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/predict-violations-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
        $this->store = new SnapshotStore(new TestProjectContext($this->tempDir));
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Build an edge that triggers a HIGH violation: Application → Infrastructure */
    private function violatingEdge(): Edge
    {
        return new Edge(
            'FlowEngine\\Application\\UseCase\\Foo::run',
            'FlowEngine\\Infrastructure\\Repo\\Bar::find',
            'find',
            'method_call'
        );
    }

    /** Build an edge that triggers a CRITICAL violation: Domain → Infrastructure */
    private function criticalEdge(): Edge
    {
        return new Edge(
            'FlowEngine\\Domain\\Entity\\Order::validate',
            'FlowEngine\\Infrastructure\\Repo\\Bar::find',
            'find',
            'method_call'
        );
    }

    /**
     * Build a minimal node set that covers all edge endpoints used in tests.
     * ArchitectureValidator::buildLayerMap() requires nodes to be present in
     * the Flow; without them getNodeLayer() falls back to 'Unknown' and no
     * violations are detected.
     *
     * Uses Node directly (not DefaultNodeFactory) to avoid file-existence validation.
     *
     * @return Node[]
     */
    private function nodesForEdge(Edge $edge): array
    {
        $make = function (string $id): Node {
            $sep    = strrpos($id, '::');
            $class  = substr($id, 0, $sep);
            $method = substr($id, $sep + 2);
            return new Node($class, $method, __FILE__, 1, 'php');
        };
        return [$make($edge->from()), $make($edge->to())];
    }

    private function makeUseCase(array $edges = [], array $customLayers = []): PredictViolations
    {
        $nodes = [];
        foreach ($edges as $edge) {
            foreach ($this->nodesForEdge($edge) as $node) {
                $nodes[$node->id()] = $node;
            }
        }
        $repo = new InMemoryFlowRepository(array_values($nodes), $edges);
        return new PredictViolations(
            new AnalyzeArchitecture($repo, $customLayers),
            $this->store,
        );
    }

    // -------------------------------------------------------------------------
    // Clean architecture
    // -------------------------------------------------------------------------

    public function test_clean_architecture_does_not_fail(): void
    {
        $dto = $this->makeUseCase()->execute('any');

        $this->assertTrue($dto->isClean);
        $this->assertFalse($dto->shouldFail);
        $this->assertSame(0, $dto->totalCurrent);
    }

    // -------------------------------------------------------------------------
    // fail-on=any
    // -------------------------------------------------------------------------

    public function test_fail_on_any_triggers_on_high_violation(): void
    {
        $dto = $this->makeUseCase([$this->violatingEdge()])->execute('any');

        $this->assertFalse($dto->isClean);
        $this->assertTrue($dto->shouldFail);
        $this->assertGreaterThan(0, $dto->totalCurrent);
    }

    public function test_fail_on_any_triggers_on_critical_violation(): void
    {
        $dto = $this->makeUseCase([$this->criticalEdge()])->execute('any');

        $this->assertTrue($dto->shouldFail);
    }

    // -------------------------------------------------------------------------
    // fail-on=critical
    // -------------------------------------------------------------------------

    public function test_fail_on_critical_does_not_trigger_on_high_only(): void
    {
        $dto = $this->makeUseCase([$this->violatingEdge()])->execute('critical');

        // HIGH violation exists but policy is critical-only
        $this->assertGreaterThan(0, $dto->totalCurrent);
        $this->assertFalse($dto->shouldFail);
    }

    public function test_fail_on_critical_triggers_on_critical_violation(): void
    {
        $dto = $this->makeUseCase([$this->criticalEdge()])->execute('critical');

        $this->assertTrue($dto->shouldFail);
    }

    // -------------------------------------------------------------------------
    // fail-on=new with baseline
    // -------------------------------------------------------------------------

    public function test_fail_on_new_with_baseline_no_new_violations(): void
    {
        // Save a baseline that already contains the violation
        $this->store->save('baseline-same', [
            'architecture' => [
                'isClean'          => false,
                'totalViolations'  => 1,
                'bySeverity'       => ['HIGH' => 1],
                'byType'           => [],
                'layerDistribution'=> [],
                'violations'       => [
                    [
                        'from'      => 'FlowEngine\\Application\\UseCase\\Foo::run',
                        'to'        => 'FlowEngine\\Infrastructure\\Repo\\Bar::find',
                        'fromLayer' => 'Application',
                        'toLayer'   => 'Infrastructure',
                        'severity'  => 'HIGH',
                        'reason'    => 'Existing',
                    ],
                ],
            ],
        ]);

        $dto = $this->makeUseCase([$this->violatingEdge()])->execute('new', 'baseline-same');

        $this->assertFalse($dto->shouldFail, 'Violation was already in baseline — not new');
        $this->assertSame(0, $dto->totalNew);
        $this->assertTrue($dto->hasBaseline);
        $this->assertSame('baseline-same', $dto->baselineLabel);
    }

    public function test_fail_on_new_with_clean_baseline_triggers_on_new_violation(): void
    {
        // Baseline had no violations
        $this->store->save('baseline-clean', [
            'architecture' => [
                'isClean'          => true,
                'totalViolations'  => 0,
                'bySeverity'       => [],
                'byType'           => [],
                'layerDistribution'=> [],
                'violations'       => [],
            ],
        ]);

        $dto = $this->makeUseCase([$this->violatingEdge()])->execute('new', 'baseline-clean');

        $this->assertTrue($dto->shouldFail);
        $this->assertSame(1, $dto->totalNew);
        $this->assertGreaterThanOrEqual(1, count($dto->newViolations));
    }

    public function test_resolved_violations_detected(): void
    {
        // Baseline had a violation that is now gone (clean codebase)
        $this->store->save('baseline-dirty', [
            'architecture' => [
                'isClean'          => false,
                'totalViolations'  => 1,
                'bySeverity'       => ['HIGH' => 1],
                'byType'           => [],
                'layerDistribution'=> [],
                'violations'       => [
                    [
                        'from'      => 'FlowEngine\\Application\\UseCase\\Foo::run',
                        'to'        => 'FlowEngine\\Infrastructure\\Repo\\Bar::find',
                        'fromLayer' => 'Application',
                        'toLayer'   => 'Infrastructure',
                        'severity'  => 'HIGH',
                        'reason'    => 'Old violation',
                    ],
                ],
            ],
        ]);

        // Current codebase has no violations
        $dto = $this->makeUseCase([])->execute('new', 'baseline-dirty');

        $this->assertFalse($dto->shouldFail);
        $this->assertSame(0, $dto->totalNew);
        $this->assertSame(1, $dto->totalResolved);
        $this->assertCount(1, $dto->resolvedViolations);
    }

    // -------------------------------------------------------------------------
    // No baseline — new mirrors current
    // -------------------------------------------------------------------------

    public function test_without_baseline_new_mirrors_current(): void
    {
        $dto = $this->makeUseCase([$this->violatingEdge()])->execute('any');

        $this->assertFalse($dto->hasBaseline);
        $this->assertNull($dto->baselineLabel);
        $this->assertSame($dto->totalCurrent, $dto->totalNew);
        $this->assertSame(0, $dto->totalResolved);
    }

    // -------------------------------------------------------------------------
    // Serialization
    // -------------------------------------------------------------------------

    public function test_toArray_and_toJson_consistent(): void
    {
        $dto = $this->makeUseCase([$this->violatingEdge()])->execute('any');
        $this->assertSame($dto->toArray(), json_decode($dto->toJson(), true));
    }
}
