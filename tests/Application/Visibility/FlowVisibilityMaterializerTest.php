<?php

namespace Tests\Application\Visibility;

use PHPUnit\Framework\TestCase;
use FlowEngine\Application\Visibility\{
    FlowVisibilityMaterializer,
    NodeVisibilityResolver
};
use FlowEngine\Application\Policy\{
    CompositeNodeVisibilityPolicy,
    DefaultNodeVisibilityPolicy
};
use FlowEngine\Domain\Flow\Flow;
use FlowEngine\Domain\Flow\Node;
use FlowEngine\Domain\Node\NodeVisibility;

final class FlowVisibilityMaterializerTest extends TestCase
{
    public function test_materializes_visibility_on_flow(): void
    {
        $policy = new CompositeNodeVisibilityPolicy([
            new DefaultNodeVisibilityPolicy(),
        ]);

        $resolver = new NodeVisibilityResolver($policy);
        $materializer = new FlowVisibilityMaterializer($resolver);

        $node = new Node(
            'App\\Service\\MyService',
            'run',
            'MyService.php',
            20
        );

        $flow = new Flow([$node], []);

        $newFlow = $materializer->materialize($flow);

        $newNode = $newFlow->nodes()[0];

        $this->assertSame(
            NodeVisibility::PUBLIC ,
            $newNode->visibility()->value()
        );
    }

}
