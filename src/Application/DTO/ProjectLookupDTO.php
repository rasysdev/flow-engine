<?php

namespace FlowEngine\Application\DTO;

/**
 * @api
 */
final readonly class ProjectLookupDTO
{
    /**
     * @param array<int, array<string, mixed>> $relatedAreas
     * @param array<int, array<string, mixed>> $directDependencies
     * @param array<int, array<string, mixed>> $directDependents
     * @param array<int, array<string, mixed>> $relevantBoundaries
     * @param array<int, array<string, mixed>> $recommendedReads
     * @param array<int, array<string, mixed>> $recommendedNextLookups
     * @param string[] $warnings
     * @param array<string, mixed>|null $context
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $kind,
        public string $targetType,
        public string $target,
        public string $whyItMatters,
        public array $relatedAreas,
        public array $directDependencies,
        public array $directDependents,
        public array $relevantBoundaries,
        public array $recommendedReads,
        public array $recommendedNextLookups,
        public array $warnings,
        public ?array $context = null,
        public array $metadata = [],
        public ?string $snippet = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $payload = [
            'kind' => $this->kind,
            'targetType' => $this->targetType,
            'target' => $this->target,
            'whyItMatters' => $this->whyItMatters,
            'relatedAreas' => $this->relatedAreas,
            'directDependencies' => $this->directDependencies,
            'directDependents' => $this->directDependents,
            'relevantBoundaries' => $this->relevantBoundaries,
            'recommendedReads' => $this->recommendedReads,
            'recommendedNextLookups' => $this->recommendedNextLookups,
            'warnings' => $this->warnings,
        ];

        if ($this->context !== null) {
            $payload['context'] = $this->context;
        }

        if ($this->metadata !== []) {
            $payload['metadata'] = $this->metadata;
        }

        if ($this->snippet !== null) {
            $payload['snippet'] = $this->snippet;
        }

        return $payload;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
