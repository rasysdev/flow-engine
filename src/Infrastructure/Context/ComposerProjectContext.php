<?php

namespace FlowEngine\Infrastructure\Context;

use FlowEngine\Domain\Contracts\ProjectConfig;
use FlowEngine\Domain\Contracts\ProjectContext;

final class ComposerProjectContext implements ProjectContext
{
    public function __construct(
        private ProjectConfig $config
    ) {
    }

    /** 
     * @internal
     */
    public function rootPath(): string
    {
        return $this->config->rootPath();
    }

    public function includePaths(): array
    {
        return $this->config->scanInclude();
    }

    /** 
     * @internal
     */
    public function autoloadPath(): ?string
    {
        return $this->config->autoloadPath()
            ? $this->rootPath() . '/' . $this->config->autoloadPath()
            : $this->rootPath() . '/vendor/autoload.php';
    }

    /** 
     * @internal
     */
    public function ignoredPaths(): array
    {
        return $this->config->scanExclude();
    }

    /** 
     * @internal
     */
    public function extensions(): array
    {
        return $this->config->scanExtensions();
    }

    /** 
     * @internal
     */
    public function boot(): void
    {
        $autoload = $this->autoloadPath();

        if ($autoload && file_exists($autoload)) {
            require_once $autoload;
        }
    }
}
