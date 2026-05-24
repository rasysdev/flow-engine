<?php

namespace FlowEngine\Application\AppMap;

final readonly class IntegrationCall
{
    /**
     * @param string $type script|http
     * @param string $fromNodeId
     * @param string $fromFile Absolute path
     * @param int $fromLine 1-based
     * @param string $target Raw target (script path or URL)
     * @param string|null $resolvedPath Absolute path for scripts (when possible)
     * @param array<string, mixed> $metadata Optional structured metadata (e.g., host/path for HTTP)
     */
    public function __construct(
        public string $type,
        public string $fromNodeId,
        public string $fromFile,
        public int $fromLine,
        public string $target,
        public ?string $resolvedPath = null,
        public array $metadata = []
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'fromNode' => $this->fromNodeId,
            'fromFile' => $this->fromFile,
            'fromLine' => $this->fromLine,
            'target' => $this->target,
            'resolvedPath' => $this->resolvedPath,
            'metadata' => $this->metadata,
        ];
    }
}
