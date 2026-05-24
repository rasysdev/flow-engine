<?php
namespace FlowEngine\AI\Context;

final class VisibilityContext
{
    /** @param array<int,array{order:int,policy:string,source:string,decision:string}> $evaluation */
    public function __construct(
        public readonly string $finalDecision,
        public readonly array $evaluation
    ) {
    }
}
