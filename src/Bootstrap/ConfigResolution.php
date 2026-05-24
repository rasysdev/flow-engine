<?php

namespace FlowEngine\Bootstrap;

/**
 * @api
 */
final readonly class ConfigResolution
{
    /**
     * @param string[] $includePaths
     * @param string[] $ignoredPaths
     * @param string[] $extensions
     * @param string[] $warnings
     */
    public function __construct(
        public string $mode,
        public string $configPath,
        public bool $hasConfigFile,
        public string $detectedContext,
        public array $includePaths,
        public array $ignoredPaths,
        public array $extensions,
        public array $warnings = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'mode' => $this->mode,
            'configPath' => $this->configPath,
            'hasConfigFile' => $this->hasConfigFile,
            'detectedContext' => $this->detectedContext,
            'includePaths' => $this->includePaths,
            'ignoredPaths' => $this->ignoredPaths,
            'extensions' => $this->extensions,
            'warnings' => $this->warnings,
        ];
    }
}
