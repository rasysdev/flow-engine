<?php

namespace Tests\Application\Policy;

use PHPUnit\Framework\TestCase;
use FlowEngine\Application\Policy\{
    StrictVisibilityStrategy,
    VisibilityDecisionResult,
    VisibilityDecision,
    VisibilityPolicySource
};
use FlowEngine\Domain\Node\NodeVisibility;
use LogicException;

final class StrictVisibilityStrategyTest extends TestCase
{
    public function test_allows_when_single_allow(): void
    {
        $strategy = new StrictVisibilityStrategy();

        $result = $strategy->compose([
            new VisibilityDecisionResult(
                'P1',
                VisibilityPolicySource::DEFAULT ,
                VisibilityDecision::ALLOW,
                new NodeVisibility(NodeVisibility::PUBLIC)
            ),
        ]);

        $this->assertTrue($result->isPublic());
    }

    public function test_throws_on_conflict(): void
    {
        $this->expectException(LogicException::class);

        $strategy = new StrictVisibilityStrategy();

        $strategy->compose([
            new VisibilityDecisionResult(
                'P1',
                VisibilityPolicySource::DEFAULT ,
                VisibilityDecision::DENY,
                new NodeVisibility(NodeVisibility::HIDDEN)
            ),
            new VisibilityDecisionResult(
                'P2',
                VisibilityPolicySource::DEFAULT ,
                VisibilityDecision::ALLOW,
                new NodeVisibility(NodeVisibility::PUBLIC)
            ),
        ]);
    }
}
