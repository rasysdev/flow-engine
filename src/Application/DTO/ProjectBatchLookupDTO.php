<?php

namespace FlowEngine\Application\DTO;

/**
 * @api
 */
final readonly class ProjectBatchLookupDTO
{
    /**
     * @param array<int, array<string, mixed>> $results
     * @param string[] $notFound
     * @param string[] $droppedTargets
     * @param string[] $warnings
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $kind,
        public string $targetType,
        public string $format,
        public int $totalRequested,
        public int $returned,
        public bool $truncated,
        public array $results,
        public array $notFound,
        public array $droppedTargets,
        public array $warnings,
        public array $metadata = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $payload = [
            'kind'           => $this->kind,
            'targetType'     => $this->targetType,
            'format'         => $this->format,
            'totalRequested' => $this->totalRequested,
            'returned'       => $this->returned,
            'truncated'      => $this->truncated,
            'results'        => $this->results,
            'notFound'       => $this->notFound,
            'droppedTargets' => $this->droppedTargets,
            'warnings'       => $this->warnings,
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
