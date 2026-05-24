<?php

namespace FlowEngine\AI\DTO;

final class SuggestedInputsResult
{
    /** @var SuggestedInput[] */
    private array $suggestions;

    /**
     * @param SuggestedInput[] $suggestions
     */
    public function __construct(array $suggestions = [])
    {
        $this->suggestions = $suggestions;
    }

    /**
     * @return SuggestedInput[]
     */
    public function suggestions(): array
    {
        return $this->suggestions;
    }

    public function isEmpty(): bool
    {
        return $this->suggestions === [];
    }
}
