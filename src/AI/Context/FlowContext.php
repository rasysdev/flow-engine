<?php
namespace FlowEngine\AI\Context;

final class FlowContext
{
    /** @param array<int,string> $nodes
     *  @param array<int,array{from:string,to:string}> $edges
     */
    public function __construct(
        public readonly array $nodes,
        public readonly array $edges,
        public readonly array $metadata = []
    ) {
    }
}
