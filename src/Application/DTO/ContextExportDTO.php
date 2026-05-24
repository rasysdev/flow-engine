<?php

namespace FlowEngine\Application\DTO;

final readonly class ContextExportDTO
{
    /**
     * @param string $markdown
     * @param int $tokenEstimate
     * @param string[] $includedSections
     */
    public function __construct(
        public string $markdown,
        public int $tokenEstimate,
        public array $includedSections
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'markdown' => $this->markdown,
            'tokenEstimate' => $this->tokenEstimate,
            'includedSections' => $this->includedSections,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }
}
