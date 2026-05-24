<?php

namespace FlowEngine\Infrastructure\Context;

use FlowEngine\Domain\Contracts\ProjectContext;

final class PhpProjectContext implements ProjectContext
{
    public function __construct(
        private string $rootPath
    ) {
    }

    /**
     * @api
     * @return void
     */
    public function boot(): void
    {
        $autoload = $this->rootPath . '/vendor/autoload.php';

        if (file_exists($autoload)) {
            require_once $autoload;
        }

        // fallback: incluir src/ direto (fixtures de teste)
        $src = $this->rootPath . '/src';
        if (is_dir($src)) {
            set_include_path(
                $src . PATH_SEPARATOR . get_include_path()
            );
        }
    }

    /**
     * @api
     * @return string
     */
    public function rootPath(): string
    {
        return $this->rootPath;
    }

    public function includePaths(): array
    {
        return ['src'];
    }

    /**
     * @api
     * @return array
     */
    public function ignoredPaths(): array
    {
        return ['vendor', 'tests'];
    }

    public function extensions(): array
    {
        return ['php'];
    }
}
