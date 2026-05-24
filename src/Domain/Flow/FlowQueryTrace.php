<?php

namespace FlowEngine\Domain\Flow;

final class FlowQueryTrace
{
    private TraceDirection $direction = TraceDirection::DOWNSTREAM;
    private int $maxDepth = 10;
    /** @var string[]|null */
    private ?array $edgeTypeFilter = null;

    public function __construct(
        private Flow $flow,
        private string $startNodeId
    ) {
    }

    public function downstream(): self
    {
        $clone = clone $this;
        $clone->direction = TraceDirection::DOWNSTREAM;
        return $clone;
    }

    public function upstream(): self
    {
        $clone = clone $this;
        $clone->direction = TraceDirection::UPSTREAM;
        return $clone;
    }

    public function both(): self
    {
        $clone = clone $this;
        $clone->direction = TraceDirection::BOTH;
        return $clone;
    }

    public function maxDepth(int $depth): self
    {
        $clone = clone $this;
        $clone->maxDepth = $depth;
        return $clone;
    }

    public function onlyMethodCalls(): self
    {
        $clone = clone $this;
        $clone->edgeTypeFilter = ['method_call'];
        return $clone;
    }

    /**
     * @param string[] $types
     */
    public function edgeTypes(array $types): self
    {
        $clone = clone $this;
        $clone->edgeTypeFilter = $types;
        return $clone;
    }

    public function trace(): FlowTrace
    {
        $tracer = new FlowTracer($this->flow);
        return $tracer->trace($this->startNodeId, $this->direction, $this->maxDepth, $this->edgeTypeFilter);
    }

    /**
     * @return Node[]
     */
    public function nodes(): array
    {
        return $this->trace()->nodes();
    }
}