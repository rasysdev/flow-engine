<?php

namespace FlowEngine\Domain\Contracts;

interface ProjectContext
{
    /**
     * Prepare the project runtime (autoload, bootstrap, etc.)
     */
    public function boot(): void;

    /**
     * @return string[] Diretórios (relativos ao rootPath) a serem ignorados
     */
    public function rootPath(): string;

    /**
     * @return string[] DiretÃ³rios (relativos ao rootPath) a serem incluÃ­dos no scan
     */
    public function includePaths(): array;

    /**
     * @return string[] DiretÃ³rios (relativos ao rootPath) a serem ignorados
     */
    public function ignoredPaths(): array;

    /**
     * @return string[] ExtensÃµes de arquivos (sem ponto), ex: ["php", "py"]
     */
    public function extensions(): array;
}
