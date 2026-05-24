<?php

namespace Tests\Support;

use FlowEngine\Domain\Contracts\ProjectContext;

final class TestProjectContext implements ProjectContext
{
    public function __construct(
        private string $rootPath,
        private array $ignoredPaths = []
    ) {
    }

    public function boot(): void
    {
        // noop — testes não precisam de bootstrap real
    }

    public function rootPath(): string
    {
        return $this->rootPath;
    }

    public function includePaths(): array
    {
        return ['src'];
    }

    public function ignoredPaths(): array
    {
        return $this->ignoredPaths;
    }

    public function extensions(): array
    {
        return ['php'];
    }
}
