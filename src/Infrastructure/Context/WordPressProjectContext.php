<?php

namespace FlowEngine\Infrastructure\Context;

use FlowEngine\Domain\Contracts\ProjectContext;

final class WordPressProjectContext implements ProjectContext
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
        /**
         * Estratégia:
         * - wp-content é onde vivem plugins e themes
         * - É onde normalmente está o código analisável
         */
        return $this->rootPath . '/wp-content';
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
        /**
         * NÃO carregar:
         * - wp-load.php
         * - wp-settings.php
         * - wp-config.php
         */

        // Define constantes mínimas para evitar fatal errors
        if (!defined('ABSPATH')) {
            define('ABSPATH', $this->rootPath . '/');
        }

        if (!defined('WPINC')) {
            define('WPINC', 'wp-includes');
        }

        // Composer autoload (se existir)
        $autoload = $this->rootPath . '/vendor/autoload.php';

        if (file_exists($autoload)) {
            require_once $autoload;
        }
    }

    /** 
     * @internal
     */
    public function ignoredPaths(): array
    {
        return [
            'wp-admin',
            'wp-includes',
            'wp-content/cache',
        ];
    }

    public function extensions(): array
    {
        return ['php'];
    }
}
