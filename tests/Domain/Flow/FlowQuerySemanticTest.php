<?php

namespace Tests\Domain\Flow;

use PHPUnit\Framework\TestCase;
use FlowEngine\Domain\Flow\Flow;
use FlowEngine\Domain\Flow\Node;

final class FlowQuerySemanticTest extends TestCase
{
    public function test_only_application_code(): void
    {
        $nodes = [
            new Node('App\\Service\\UserService', 'run', 'a.php', null),
            new Node('Illuminate\\Support\\Collection', 'make', 'b.php', null),
        ];

        $flow = new Flow($nodes, []);

        $result = $flow
            ->query()
            ->onlyApplicationCode()
            ->all();

        $this->assertCount(1, $result);
        $this->assertSame('App\\Service\\UserService::run', $result[0]->id()); // ← Corrigido
    }

    public function test_describe_does_not_break_chain(): void
    {
        $nodes = [
            new Node('App\\Service\\A', 'x', 'a.php', null),
        ];

        $flow = new Flow($nodes, []);

        $result = $flow
            ->query()
            ->describe('public services')
            ->publicNodes()
            ->all();

        $this->assertCount(1, $result);
    }
}
