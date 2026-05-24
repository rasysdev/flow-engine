<?php

namespace FlowEngine\Application\DTO;

/**
 * @api
 */
final readonly class ProjectMapDTO
{
    /**
     * @param array<string, mixed> $purpose
     * @param array<string, mixed> $project
     * @param array<string, mixed> $capabilities
     * @param array<string, mixed> $structure
     * @param array<string, mixed> $navigation
     * @param string[] $warnings
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $kind,
        public string $scope,
        public string $mode,
        public array $purpose,
        public array $project,
        public array $capabilities,
        public array $structure,
        public array $navigation,
        public array $warnings,
        public array $metadata = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $payload = [
            'kind' => $this->kind,
            'scope' => $this->scope,
            'mode' => $this->mode,
            'purpose' => $this->purpose,
            'project' => $this->project,
            'capabilities' => $this->capabilities,
            'structure' => $this->structure,
            'navigation' => $this->navigation,
            'warnings' => $this->warnings,
        ];

        if ($this->metadata !== []) {
            $payload['metadata'] = $this->metadata;
        }

        return $payload;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
