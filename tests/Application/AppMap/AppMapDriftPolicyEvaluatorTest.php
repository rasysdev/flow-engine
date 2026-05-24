<?php

namespace Tests\Application\AppMap;

use FlowEngine\Application\AppMap\AppMapDriftPolicyEvaluator;
use PHPUnit\Framework\TestCase;

final class AppMapDriftPolicyEvaluatorTest extends TestCase
{
    public function test_it_fails_when_threshold_and_blockers_are_hit(): void
    {
        $diff = [
            'summary' => [
                'servicesAdded' => 2,
                'servicesRemoved' => 0,
                'serviceEdgesAdded' => 3,
                'serviceEdgesRemoved' => 0,
                'inconsistenciesAdded' => 1,
                'inconsistenciesResolved' => 0,
                'breakingChanges' => 0,
            ],
            'serviceEdges' => [
                'addedDetails' => [
                    ['type' => 'http'],
                    ['type' => 'script'],
                ],
            ],
            'inconsistencies' => [
                'addedDetails' => [
                    ['severity' => 'high'],
                ],
            ],
        ];

        $policy = [
            'version' => '1.0',
            'thresholds' => [
                'servicesAddedMax' => 1,
                'serviceEdgesAddedMax' => 2,
            ],
            'blockers' => [
                'edgeTypes' => ['script'],
                'inconsistencySeverities' => ['high'],
            ],
        ];

        $result = (new AppMapDriftPolicyEvaluator())->evaluate($diff, $policy);

        self::assertFalse($result['passed']);
        self::assertNotEmpty($result['reasons']);
    }

    public function test_it_fails_when_breaking_dependency_changes_are_blocked(): void
    {
        $diff = [
            'summary' => [
                'servicesAdded' => 0,
                'servicesRemoved' => 1,
                'serviceEdgesAdded' => 0,
                'serviceEdgesRemoved' => 1,
                'inconsistenciesAdded' => 0,
                'inconsistenciesResolved' => 0,
                'breakingChanges' => 2,
            ],
            'serviceEdges' => ['addedDetails' => []],
            'inconsistencies' => ['addedDetails' => []],
            'breakingChanges' => ['total' => 2],
        ];

        $policy = [
            'version' => '1.0',
            'blockers' => [
                'breakingDependencyChanges' => true,
            ],
        ];

        $result = (new AppMapDriftPolicyEvaluator())->evaluate($diff, $policy);

        self::assertFalse($result['passed']);
        self::assertContains('Blocked breaking dependency changes detected: 2', $result['reasons']);
    }
}
