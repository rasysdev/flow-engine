<?php

namespace FlowEngine\Infrastructure\Context;

use FlowEngine\Domain\Contracts\ProjectContext;

final class Yii2ProjectContext implements ProjectContext
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
        $autoload = $this->rootPath . '/vendor/autoload.php';

        if (file_exists($autoload)) {
            require_once $autoload;
        }

        // Yii2 não é inicializado
        // Apenas autoload para Reflection funcionar
    }

    /**
     * @internal
     * @return string[]
     */
    public function ignoredPaths(): array
    {
        return ['vendor', 'runtime'];
    }

    public function extensions(): array
    {
        return ['php'];
    }
}
