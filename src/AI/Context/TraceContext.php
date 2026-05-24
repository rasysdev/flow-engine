<?php

namespace FlowEngine\AI\Context;

final class TraceContext
{
    /**
     * @param string $nodeId
     * @param string[] $upstream
     * @param string[] $downstream
     */
    public function __construct(
        public readonly string $nodeId,
        public readonly array $upstream,
        public readonly array $downstream
    ) {
    }
}
