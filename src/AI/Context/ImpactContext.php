<?php
namespace FlowEngine\AI\Context;

final class ImpactContext
{
    /** @param string[] $upstream
     *  @param string[] $downstream
     */
    public function __construct(
        public readonly array $upstream,
        public readonly array $downstream
    ) {
    }
}
