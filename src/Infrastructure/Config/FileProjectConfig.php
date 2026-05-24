<?php

namespace FlowEngine\Infrastructure\Config;

use FlowEngine\Domain\Contracts\ProjectConfig;

final class FileProjectConfig implements ProjectConfig
{
    public function __construct(
        private array $data,
        private string $rootPath
    ) {
    }

    public function version(): string
    {
        return $this->data['version'] ?? '1.0';
    }

    public function rootPath(): string
    {
        return $this->rootPath;
    }

    public function scanInclude(): array
    {
        if (isset($this->data['scan']['include']) && is_array($this->data['scan']['include'])) {
            return $this->data['scan']['include'];
        }

        if ($this->contextType() === 'flutter') {
            return $this->defaultFlutterInclude();
        }

        return ['src'];
    }

    public function scanExclude(): array
    {
        if (isset($this->data['scan']['exclude']) && is_array($this->data['scan']['exclude'])) {
            return $this->data['scan']['exclude'];
        }

        if ($this->contextType() === 'flutter') {
            return $this->defaultFlutterExclude();
        }

        return [];
    }

    public function scanExtensions(): array
    {
        if (isset($this->data['scan']['extensions']) && is_array($this->data['scan']['extensions'])) {
            return $this->data['scan']['extensions'];
        }

        if ($this->contextType() === 'flutter') {
            return $this->defaultFlutterExtensions();
        }

        return ['php'];
    }

    public function contextType(): string
    {
        return $this->data['context']['type'] ?? 'composer';
    }

    public function autoloadPath(): ?string
    {
        return $this->data['context']['autoload'] ?? null;
    }

    public function nodeVisibilityRules(): array
    {
        return $this->data['nodes']['visibility']['rules'] ?? [];
    }

    public function defaultNodeVisibility(): string
    {
        return $this->data['nodes']['visibility']['default'] ?? 'public';
    }

    public function architectureLayers(): array
    {
        return $this->data['architecture']['layers'] ?? [];
    }

    public function livewireNamespace(): string
    {
        return $this->data['livewire']['namespace'] ?? 'App\\Http\\Livewire';
    }

    public function entrypointPatterns(): array
    {
        return $this->data['entrypoints']['patterns'] ?? [];
    }

    public function snapshotRetention(): ?int
    {
        $value = $this->data['snapshots']['keep'] ?? null;

        if (!is_int($value) || $value < 1) {
            return null;
        }

        return $value;
    }

    public function flutterConfig(): array
    {
        $config = $this->data['flutter'] ?? [];
        return is_array($config) ? $config : [];
    }

    /**
     * @return string[]
     */
    private function defaultFlutterInclude(): array
    {
        $include = ['lib', 'test', 'integration_test'];

        if (is_dir($this->rootPath . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'app')) {
            $include[] = 'backend/app';
        }

        return $include;
    }

    /**
     * @return string[]
     */
    private function defaultFlutterExclude(): array
    {
        return [
            '.dart_tool',
            '.pub-cache',
            'build',
            'backend/.pytest_cache',
            'backend/tmp',
            'ios/Pods',
        ];
    }

    /**
     * @return string[]
     */
    private function defaultFlutterExtensions(): array
    {
        $extensions = ['dart'];

        if (is_dir($this->rootPath . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'app')) {
            $extensions[] = 'py';
        }

        return $extensions;
    }
}
