<?php

namespace Tests\Domain\Analysis;

use FlowEngine\Domain\Analysis\Bug;
use FlowEngine\Domain\Analysis\BugDetector;
use FlowEngine\Domain\Analysis\MetricsAnalyzer;
use FlowEngine\Domain\Flow\Edge;
use FlowEngine\Domain\Flow\Flow;
use FlowEngine\Domain\Flow\Node;
use PHPUnit\Framework\TestCase;

final class BugDetectorTest extends TestCase
{
    private function makeFlow(array $nodes = [], array $edges = []): Flow
    {
        return new Flow($nodes, $edges);
    }

    private function rawFinding(string $nodeId, string $type = 'exception_swallowing', float $confidence = 0.9): array
    {
        return [
            'nodeId'      => $nodeId,
            'type'        => $type,
            'description' => 'Test finding',
            'confidence'  => $confidence,
            'file'        => '/path/to/File.php',
            'line'        => 10,
        ];
    }

    public function test_empty_findings_returns_no_bugs(): void
    {
        $flow     = $this->makeFlow();
        $detector = new BugDetector($flow, new MetricsAnalyzer($flow));

        $bugs = $detector->detectBugs([]);

        $this->assertSame([], $bugs);
    }

    public function test_bug_score_increases_with_higher_fanin(): void
    {
        // NodeA has fanIn=10 (many callers), NodeB has fanIn=1
        $nodeA = new Node('App\\ServiceA', 'handle', '/a.php', 1);
        $nodeB = new Node('App\\ServiceB', 'handle', '/b.php', 2);
        $caller = new Node('App\\Caller', 'run', '/c.php', 3);

        // 10 edges → NodeA, 1 edge → NodeB
        $edges = [new Edge('App\\Caller::run', 'App\\ServiceB::handle', 'method')];
        for ($i = 0; $i < 10; $i++) {
            $edges[] = new Edge("App\\X{$i}::call", 'App\\ServiceA::handle', 'method');
            $nodes[] = new Node("App\\X{$i}", 'call', "/x{$i}.php", $i + 10);
        }
        $nodes[] = $nodeA;
        $nodes[] = $nodeB;
        $nodes[] = $caller;

        $flow     = new Flow($nodes, $edges);
        $detector = new BugDetector($flow, new MetricsAnalyzer($flow));

        $findings = [
            $this->rawFinding('App\\ServiceA::handle', 'exception_swallowing', 0.9),
            $this->rawFinding('App\\ServiceB::handle', 'exception_swallowing', 0.9),
        ];

        $bugs = $detector->detectBugs($findings);

        $this->assertCount(2, $bugs);

        $scoreA = null;
        $scoreB = null;
        foreach ($bugs as $bug) {
            if ($bug->nodeId === 'App\\ServiceA::handle') {
                $scoreA = $bug->bugScore;
            } elseif ($bug->nodeId === 'App\\ServiceB::handle') {
                $scoreB = $bug->bugScore;
            }
        }

        $this->assertNotNull($scoreA);
        $this->assertNotNull($scoreB);
        $this->assertGreaterThan($scoreB, $scoreA, 'High fanIn node should have a higher bug score');
    }

    public function test_critical_severity_at_70_plus(): void
    {
        // fanIn=33, confidence=1.0:
        // blastRadius=33 (all callers are upstream of target)
        // score = round(1.0 * (1 + 33*2 + 33/10)) = round(70.3) = 70 → CRITICAL
        $nodes = [];
        $edges = [];
        for ($i = 0; $i < 33; $i++) {
            $nodes[] = new Node("App\\C{$i}", 'call', "/c{$i}.php", $i);
            $edges[] = new Edge("App\\C{$i}::call", 'App\\Target::handle', 'method');
        }
        $nodes[] = new Node('App\\Target', 'handle', '/target.php', 99);

        $flow     = new Flow($nodes, $edges);
        $detector = new BugDetector($flow, new MetricsAnalyzer($flow));

        $bugs = $detector->detectBugs([$this->rawFinding('App\\Target::handle', 'resource_leak', 1.0)]);

        $this->assertCount(1, $bugs);
        $this->assertSame('CRITICAL', $bugs[0]->severity);
        $this->assertGreaterThanOrEqual(70, $bugs[0]->bugScore);
    }

    public function test_high_severity_at_40_to_69(): void
    {
        // fanIn=10, blastRadius=10, confidence=1.0:
        // score = round(1.0 * (1 + 10*2 + 10/10)) = round(22) = 22 — still MEDIUM
        // fanIn=15, blastRadius=15, confidence=1.0:
        // score = round(1.0 * (1 + 30 + 1.5)) = round(32.5) = 33 — still MEDIUM
        // fanIn=18, blastRadius=18, confidence=1.0:
        // score = round(1.0 * (1 + 36 + 1.8)) = round(38.8) = 39 — still MEDIUM
        // fanIn=19, blastRadius=19, confidence=1.0:
        // score = round(1.0 * (1 + 38 + 1.9)) = round(40.9) = 41 — HIGH ✓
        $nodes = [];
        $edges = [];
        for ($i = 0; $i < 19; $i++) {
            $nodes[] = new Node("App\\D{$i}", 'call', "/d{$i}.php", $i);
            $edges[] = new Edge("App\\D{$i}::call", 'App\\TargetHigh::handle', 'method');
        }
        $nodes[] = new Node('App\\TargetHigh', 'handle', '/high.php', 99);

        $flow     = new Flow($nodes, $edges);
        $detector = new BugDetector($flow, new MetricsAnalyzer($flow));

        $bugs = $detector->detectBugs([$this->rawFinding('App\\TargetHigh::handle', 'resource_leak', 1.0)]);

        $this->assertCount(1, $bugs);
        $this->assertSame('HIGH', $bugs[0]->severity);
        $this->assertGreaterThanOrEqual(40, $bugs[0]->bugScore);
        $this->assertLessThan(70, $bugs[0]->bugScore);
    }

    public function test_min_score_filter_excludes_low_scores(): void
    {
        // Node not in graph → fallback: round(0.5 * 20) = 10
        $flow     = $this->makeFlow();
        $detector = new BugDetector($flow, new MetricsAnalyzer($flow));

        $bugs = $detector->detectBugs(
            [$this->rawFinding('Ghost\\Service::handle', 'missing_return_type', 0.5)],
            minScore: 50
        );

        $this->assertSame([], $bugs);
    }

    public function test_stats_returns_correct_by_severity_and_type(): void
    {
        $flow     = $this->makeFlow();
        $detector = new BugDetector($flow, new MetricsAnalyzer($flow));

        // All fallback scores: confidence*20
        // 0.9*20=18 → LOW, 0.5*20=10 → LOW, 0.9*20=18 → LOW
        $findings = [
            $this->rawFinding('A::a', 'exception_swallowing', 0.9),
            $this->rawFinding('B::b', 'missing_return_type', 0.5),
            $this->rawFinding('C::c', 'exception_swallowing', 0.9),
        ];

        $bugs  = $detector->detectBugs($findings);
        $stats = $detector->stats($bugs);

        $this->assertSame(3, $stats['totalBugs']);
        $this->assertSame(2, $stats['byType']['exception_swallowing']);
        $this->assertSame(1, $stats['byType']['missing_return_type']);
        $this->assertArrayHasKey('bySeverity', $stats);
    }

    public function test_graceful_degradation_gets_info_severity_and_zero_score(): void
    {
        $flow     = $this->makeFlow();
        $detector = new BugDetector($flow, new MetricsAnalyzer($flow));

        $finding = $this->rawFinding('App\\Svc::handle', 'graceful_degradation', 0.9);
        $bugs    = $detector->detectBugs([$finding]);

        $this->assertCount(1, $bugs);
        $this->assertSame('INFO', $bugs[0]->severity);
        $this->assertSame(0, $bugs[0]->bugScore);
    }

    public function test_graceful_degradation_does_not_count_in_total_bugs(): void
    {
        $flow     = $this->makeFlow();
        $detector = new BugDetector($flow, new MetricsAnalyzer($flow));

        $findings = [
            $this->rawFinding('A::a', 'graceful_degradation', 0.9),
            $this->rawFinding('B::b', 'graceful_degradation', 0.9),
            $this->rawFinding('C::c', 'exception_swallowing', 0.9),
        ];

        $bugs  = $detector->detectBugs($findings);
        $stats = $detector->stats($bugs);

        $this->assertSame(1, $stats['totalBugs'], 'Only exception_swallowing counts as bug');
        $this->assertSame(2, $stats['bySeverity']['INFO']);
        $this->assertSame(2, $stats['byType']['graceful_degradation']);
    }

    public function test_bugs_are_sorted_descending_by_score(): void
    {
        // Nodes in graph with different fanIn values
        $nodes = [
            new Node('App\\High', 'go', '/h.php', 1),
            new Node('App\\Low', 'go', '/l.php', 2),
        ];
        $edges = [
            new Edge('App\\X::x', 'App\\High::go', 'method'),
            new Edge('App\\Y::y', 'App\\High::go', 'method'),
            new Edge('App\\Y::y', 'App\\High::go', 'method'),
        ];
        // Actually let's avoid duplicate edges; use distinct sources
        $edges = [
            new Edge('App\\X::x', 'App\\High::go', 'method'),
            new Edge('App\\Y::y', 'App\\High::go', 'method'),
        ];
        $nodes[] = new Node('App\\X', 'x', '/x.php', 3);
        $nodes[] = new Node('App\\Y', 'y', '/y.php', 4);

        $flow     = new Flow($nodes, $edges);
        $detector = new BugDetector($flow, new MetricsAnalyzer($flow));

        $findings = [
            $this->rawFinding('App\\Low::go', 'missing_return_type', 0.5),
            $this->rawFinding('App\\High::go', 'exception_swallowing', 0.9),
        ];

        $bugs = $detector->detectBugs($findings);

        $this->assertCount(2, $bugs);
        $this->assertGreaterThanOrEqual($bugs[1]->bugScore, $bugs[0]->bugScore);
    }
}
