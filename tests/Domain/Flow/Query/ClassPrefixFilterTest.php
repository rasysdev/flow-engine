<?php

namespace Tests\Domain\Flow\Query;

use PHPUnit\Framework\TestCase;
use FlowEngine\Domain\Flow\Node;
use FlowEngine\Domain\Flow\NodeCollection;
use FlowEngine\Domain\Flow\Query\Filters\ClassPrefixFilter;

final class ClassPrefixFilterTest extends TestCase
{
    public function test_it_filters_nodes_by_class_prefix(): void
    {
        $nodes = new NodeCollection([
            new Node('App\\Service\\User', 'run', 'a.php', null),
            new Node('Illuminate\\Support\\Collection', 'make', 'b.php', null),
        ]);

        $filter = new ClassPrefixFilter('App\\');

        $result = $filter->apply($nodes)->all();

        $this->assertCount(1, $result);
        $this->assertSame('App\\Service\\User::run', $result[0]->id());
    }
}
