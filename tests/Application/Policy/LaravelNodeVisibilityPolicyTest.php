<?php

namespace Tests\Application\Policy;

use PHPUnit\Framework\TestCase;
use FlowEngine\Application\Policy\LaravelNodeVisibilityPolicy;
use FlowEngine\Domain\Flow\Node;
use FlowEngine\Domain\Node\NodeVisibility;

final class LaravelNodeVisibilityPolicyTest extends TestCase
{
    public function test_it_hides_illuminate_classes(): void
    {
        $policy = new LaravelNodeVisibilityPolicy();

        $node = new Node(
            'Illuminate\\Support\\Collection',
            'make',
            'Collection.php',
            10
        );

        $visibility = $policy->visibility($node);

        $this->assertInstanceOf(NodeVisibility::class, $visibility);
        $this->assertSame(NodeVisibility::HIDDEN, $visibility->value());
        $this->assertFalse($visibility->isPublic());
    }

    public function test_it_does_not_decide_for_user_classes(): void
    {
        $policy = new LaravelNodeVisibilityPolicy();

        $node = new Node(
            'App\\Service\\MyService',
            'run',
            'MyService.php',
            20
        );

        $visibility = $policy->visibility($node);

        $this->assertNull($visibility);
    }
}
