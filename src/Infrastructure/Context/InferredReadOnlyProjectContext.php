<?php

namespace FlowEngine\Infrastructure\Context;

use FlowEngine\Domain\Contracts\ProjectContext;

final class InferredReadOnlyProjectContext implements ProjectContext
{
    /**
     * @param string[] $includePaths
     * @param string[] $ignoredPaths
     * @param string[] $extensions
     */
    public function __construct(
        private string $rootPath,
        private array $includePaths,
        private array $ignoredPaths,
        private array $extensions,
        private bool $defineWordPressConstants = false,
    ) {
    }

    public function boot(): void
    {
        if ($this->defineWordPressConstants) {
            if (!defined('ABSPATH')) {
                define('ABSPATH', $this->rootPath . DIRECTORY_SEPARATOR);
            }

            if (!defined('WPINC')) {
                define('WPINC', 'wp-includes');
            }
        }
    }

    public function rootPath(): string
    {
        return $this->rootPath;
    }

    public function includePaths(): array
    {
        return $this->includePaths;
    }

    public function ignoredPaths(): array
    {
        return $this->ignoredPaths;
    }

    public function extensions(): array
    {
        return $this->extensions;
    }
}
