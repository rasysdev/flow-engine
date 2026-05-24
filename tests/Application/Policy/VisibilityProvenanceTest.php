<?php

namespace Tests\Application\Policy;

use PHPUnit\Framework\TestCase;
use FlowEngine\Application\Policy\{
    CompositeNodeVisibilityPolicy,
    PolicyWithSource,
    VisibilityPolicySource,
    VisibilityDecision
};
use FlowEngine\Application\Policy\DefaultNodeVisibilityPolicy;
use FlowEngine\Application\Policy\LaravelNodeVisibilityPolicy;
use FlowEngine\Domain\Flow\Node;

final class VisibilityProvenanceTest extends TestCase
{
    public function test_provenance_is_recorded(): void
    {
        $policy = new CompositeNodeVisibilityPolicy([
            new PolicyWithSource(
                new LaravelNodeVisibilityPolicy(),   // ABSTAIN neste node
                VisibilityPolicySource::FRAMEWORK
            ),
            new PolicyWithSource(
                new DefaultNodeVisibilityPolicy(),   // ALLOW
                VisibilityPolicySource::DEFAULT
            ),
        ]);

        $node = new Node(
            'App\\Service\\MyService', // não Illuminate → Laravel policy ABSTAINS
            'run',
            'MyService.php',
            20
        );

        $policy->visibility($node);
        $report = $policy->report();

        $results = $report->results();

        $this->assertCount(2, $results);

        $this->assertSame(
            VisibilityPolicySource::FRAMEWORK,
            $results[0]->source
        );
        $this->assertSame(
            VisibilityDecision::ABSTAIN,
            $results[0]->decision
        );

        $this->assertSame(
            VisibilityPolicySource::DEFAULT ,
            $results[1]->source
        );
        $this->assertSame(
            VisibilityDecision::ALLOW,
            $results[1]->decision
        );
    }
}
