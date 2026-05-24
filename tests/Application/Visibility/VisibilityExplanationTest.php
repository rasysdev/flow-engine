<?php

namespace Tests\Application\Visibility;

use PHPUnit\Framework\TestCase;
use FlowEngine\Application\Visibility\{
    NodeVisibilityResolver,
    VisibilityExplanation
};
use FlowEngine\Application\Policy\{
    CompositeNodeVisibilityPolicy,
    DefaultNodeVisibilityPolicy,
    PolicyWithSource,
    VisibilityPolicySource
};
use FlowEngine\Domain\Flow\Node;

final class VisibilityExplanationTest extends TestCase
{
    public function test_visibility_is_explainable(): void
    {
        $policy = new CompositeNodeVisibilityPolicy([
            new PolicyWithSource(
                new DefaultNodeVisibilityPolicy(),
                VisibilityPolicySource::DEFAULT
            ),
        ]);

        $resolver = new NodeVisibilityResolver($policy);

        $node = new Node(
            'App\\Service\\MyService',
            'run',
            'MyService.php',
            20
        );

        $explanation = $resolver->explain($node);

        $this->assertInstanceOf(VisibilityExplanation::class, $explanation);
        $this->assertSame($node->id(), $explanation->nodeId);
        $this->assertCount(1, $explanation->items);
        $this->assertSame(
            VisibilityPolicySource::DEFAULT ,
            $explanation->items[0]->source
        );
    }
}
