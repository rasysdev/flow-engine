<?php

namespace FlowEngine\Domain\Flow\Query;

final class FlowQueryDefinition
{
    /** @var FlowQueryFilter[] */
    private array $filters = [];

    private ?string $intent = null;

    public function addFilter(FlowQueryFilter $filter): void
    {
        $this->filters[] = $filter;
    }

    /**
     * @return FlowQueryFilter[]
     */
    public function filters(): array
    {
        return $this->filters;
    }

    public function describe(string $intent): void
    {
        $this->intent = $intent;
    }

    public function intent(): ?string
    {
        return $this->intent;
    }
}
