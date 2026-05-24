<?php

namespace FlowEngine\Infrastructure\Context;

use FlowEngine\Domain\Contracts\ProjectContext;

final class LaravelProjectContext implements ProjectContext
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
        return $this->rootPath . '/app';
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
        $autoload = $this->rootPath . '/vendor/autoload.php';

        if (file_exists($autoload)) {
            require_once $autoload;
        }

        // Não inicializamos o kernel HTTP
        // Apenas garantimos autoload e helpers
    }

    /**
     * @internal
     * @return string[]
     */
    public function ignoredPaths(): array
    {
        return ['vendor', 'node_modules', 'storage', 'bootstrap/cache'];
    }

    public function extensions(): array
    {
        return ['php'];
    }
}
