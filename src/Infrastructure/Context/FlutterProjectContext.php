<?php

namespace FlowEngine\Infrastructure\Context;

use FlowEngine\Domain\Contracts\ProjectConfig;
use FlowEngine\Domain\Contracts\ProjectContext;

final class FlutterProjectContext implements ProjectContext
{
    private ?ProjectConfig $config = null;
    private string $rootPath;

    public function __construct(ProjectConfig|string $configOrRootPath)
    {
        if ($configOrRootPath instanceof ProjectConfig) {
            $this->config = $configOrRootPath;
            $this->rootPath = $configOrRootPath->rootPath();
            return;
        }

        $this->rootPath = $configOrRootPath;
    }

    public function boot(): void
    {
        // Flutter support is static-only in v1.
    }

    public function rootPath(): string
    {
        return $this->rootPath;
    }

    public function includePaths(): array
    {
        if ($this->config !== null) {
            return $this->config->scanInclude();
        }

        $include = ['lib', 'test', 'integration_test'];

        if (is_dir($this->rootPath . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'app')) {
            $include[] = 'backend/app';
        }

        return $include;
    }

    public function ignoredPaths(): array
    {
        if ($this->config !== null) {
            return $this->config->scanExclude();
        }

        return [
            '.dart_tool',
            '.pub-cache',
            'build',
            'backend/.pytest_cache',
            'backend/tmp',
            'ios/Pods',
        ];
    }

    public function extensions(): array
    {
        if ($this->config !== null) {
            return $this->config->scanExtensions();
        }

        $extensions = ['dart'];

        if (is_dir($this->rootPath . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'app')) {
            $extensions[] = 'py';
        }

        return $extensions;
    }
}
