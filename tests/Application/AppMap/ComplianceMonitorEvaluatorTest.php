<?php

namespace Tests\Application\AppMap;

use FlowEngine\Application\AppMap\ComplianceMonitorEvaluator;
use PHPUnit\Framework\TestCase;

final class ComplianceMonitorEvaluatorTest extends TestCase
{
    public function test_it_returns_pass_when_no_policy_violation_is_detected(): void
    {
        $diff = [
            'summary' => [
                'servicesAdded' => 0,
                'servicesRemoved' => 0,
                'serviceEdgesAdded' => 0,
                'serviceEdgesRemoved' => 0,
                'inconsistenciesAdded' => 0,
                'breakingChanges' => 0,
            ],
            'serviceEdges' => ['addedDetails' => []],
            'inconsistencies' => ['addedDetails' => []],
            'breakingChanges' => ['total' => 0],
        ];

        $policy = [
            'version' => '1.0',
            'thresholds' => ['servicesAddedMax' => 1],
            'blockers' => ['breakingDependencyChanges' => true],
        ];

        $monitor = (new ComplianceMonitorEvaluator())->evaluate($diff, $policy);

        self::assertSame('pass', $monitor['status']);
        self::assertFalse($monitor['approvalRequired']);
        self::assertSame(0, $monitor['riskSummary']['totalFindings']);
        self::assertSame([], $monitor['topReasons']);
        self::assertSame([], $monitor['violations']);
    }

    public function test_it_returns_warn_when_only_threshold_is_exceeded(): void
    {
        $diff = [
            'summary' => [
                'servicesAdded' => 2,
                'servicesRemoved' => 0,
                'serviceEdgesAdded' => 0,
                'serviceEdgesRemoved' => 0,
                'inconsistenciesAdded' => 0,
                'breakingChanges' => 0,
            ],
            'serviceEdges' => ['addedDetails' => []],
            'inconsistencies' => ['addedDetails' => []],
            'breakingChanges' => ['total' => 0],
        ];

        $policy = [
            'version' => '1.0',
            'thresholds' => ['servicesAddedMax' => 1],
        ];

        $monitor = (new ComplianceMonitorEvaluator())->evaluate($diff, $policy);

        self::assertSame('warn', $monitor['status']);
        self::assertTrue($monitor['approvalRequired']);
        self::assertSame(1, $monitor['riskSummary']['warnCount']);
        self::assertSame(0, $monitor['riskSummary']['failCount']);
        self::assertSame('THRESHOLD_EXCEEDED_SERVICESADDED', $monitor['violations'][0]['code']);
    }

    public function test_it_returns_fail_when_blocker_is_triggered(): void
    {
        $diff = [
            'summary' => [
                'servicesAdded' => 1,
                'servicesRemoved' => 1,
                'serviceEdgesAdded' => 1,
                'serviceEdgesRemoved' => 1,
                'inconsistenciesAdded' => 0,
                'breakingChanges' => 2,
            ],
            'serviceEdges' => [
                'addedDetails' => [
                    ['type' => 'script'],
                ],
            ],
            'inconsistencies' => ['addedDetails' => []],
            'breakingChanges' => ['total' => 2],
        ];

        $policy = [
            'version' => '1.0',
            'thresholds' => ['servicesAddedMax' => 0],
            'blockers' => [
                'edgeTypes' => ['script'],
                'breakingDependencyChanges' => true,
            ],
        ];

        $monitor = (new ComplianceMonitorEvaluator())->evaluate($diff, $policy);

        self::assertSame('fail', $monitor['status']);
        self::assertTrue($monitor['approvalRequired']);
        self::assertGreaterThanOrEqual(1, $monitor['riskSummary']['failCount']);
        self::assertSame('fail', $monitor['violations'][0]['severity']);
        self::assertNotEmpty($monitor['topReasons']);
    }
}
