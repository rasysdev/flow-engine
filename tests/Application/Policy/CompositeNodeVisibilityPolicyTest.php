<?php

namespace Tests\Application\Policy;

use PHPUnit\Framework\TestCase;
use FlowEngine\Application\Policy\CompositeNodeVisibilityPolicy;
use FlowEngine\Application\Policy\DefaultNodeVisibilityPolicy;
use FlowEngine\Application\Policy\LaravelNodeVisibilityPolicy;
use FlowEngine\Domain\Flow\Node;
use LogicException;

final class CompositeNodeVisibilityPolicyTest extends TestCase
{
    public function test_order_is_respected(): void
    {
        $policy = new CompositeNodeVisibilityPolicy([
            new LaravelNodeVisibilityPolicy(), // ABSTAIN neste caso
            new DefaultNodeVisibilityPolicy(), // ALLOW
        ]);

        $node = new Node(
            'App\\Service\\MyService',
            'run',
            'MyService.php',
            20
        );

        $visibility = $policy->visibility($node);

        $this->assertTrue($visibility->isPublic());
    }

    public function test_default_applies_when_all_abstain(): void
    {
        $policy = new CompositeNodeVisibilityPolicy([
            new LaravelNodeVisibilityPolicy(),
            new DefaultNodeVisibilityPolicy(),
        ]);

        $node = new Node(
            'App\\Service\\MyService',
            'run',
            'MyService.php',
            20
        );

        $visibility = $policy->visibility($node);

        $this->assertTrue($visibility->isPublic());
    }

    public function test_conflicting_policies_are_detected(): void
    {
        $this->expectException(LogicException::class);

        $policy = new CompositeNodeVisibilityPolicy([
            new DefaultNodeVisibilityPolicy(),
            new LaravelNodeVisibilityPolicy(),
        ]);

        $node = new Node(
            'Illuminate\\Support\\Collection',
            'make',
            'Collection.php',
            10
        );

        $policy->visibility($node);
    }
}
