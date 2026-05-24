<?php

namespace FlowEngine\Infrastructure\Context;

use FlowEngine\Domain\Contracts\ProjectContext;

final class GenericPhpProjectContext implements ProjectContext
{
    public function __construct(
        private string $rootPath
    ) {
    }

    /** 
     * @internal
     */
    public function rootPath(): string
    {
        return $this->rootPath;
    }

    public function includePaths(): array
    {
        return ['.'];
    }

    /**
     * @internal
     */
    public function boot(): void
    {
        // Nada a fazer por padrão
        // Projetos legados não precisam de bootstrap
    }

    /**
     * @internal
     * @return string[]
     */
    public function ignoredPaths(): array
    {
        return ['vendor', 'node_modules'];
    }

    public function extensions(): array
    {
        return ['php'];
    }
}
