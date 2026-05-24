<?php

namespace Tests\Application\Visibility;

use PHPUnit\Framework\TestCase;
use FlowEngine\Application\Visibility\NodeVisibilityResolver;
use FlowEngine\Application\Policy\{
    CompositeNodeVisibilityPolicy,
    PolicyWithSource,
    VisibilityPolicySource
};
use FlowEngine\Application\Policy\LaravelNodeVisibilityPolicy;
use FlowEngine\Application\Policy\DefaultNodeVisibilityPolicy;
use FlowEngine\Domain\Flow\Node;

final class NodeVisibilityResolverTest extends TestCase
{
    public function test_resolves_visibility_via_governance(): void
    {
        $policy = new CompositeNodeVisibilityPolicy([
            new PolicyWithSource(
                new LaravelNodeVisibilityPolicy(), // ABSTAIN
                VisibilityPolicySource::FRAMEWORK
            ),
            new PolicyWithSource(
                new DefaultNodeVisibilityPolicy(), // ALLOW
                VisibilityPolicySource::DEFAULT
            ),
        ]);

        $resolver = new NodeVisibilityResolver($policy);

        $node = new Node(
            'App\\Service\\MyService', // não Illuminate
            'run',
            'MyService.php',
            20
        );

        $visibility = $resolver->resolve($node);

        $this->assertTrue($visibility->isPublic());
    }

}
