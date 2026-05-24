<?php

namespace FlowEngine\AI\Export;

final readonly class ExportOptions
{
    public function __construct(
        public bool $includeMetrics = true,
        public bool $includeComplexity = true,
        public bool $includeCycles = true,
        public bool $includeArchitecture = true,
        public bool $includeOrphans = true,
        public bool $includeServiceMap = false,
        public bool $includeDataModel = false,
        public bool $includeRoutes = false,
        public ?string $focusNamespace = null,
        public ?string $catalogPath = null,
        public ?string $entrypoint = null,
        public int $entrypointDepth = 5,
        public bool $strictEntrypoint = false,
    ) {
    }

    public static function all(): self
    {
        return new self();
    }

    public static function minimal(): self
    {
        return new self(
            includeMetrics: true,
            includeComplexity: false,
            includeCycles: false,
            includeArchitecture: false,
            includeOrphans: false
        );
    }
}
