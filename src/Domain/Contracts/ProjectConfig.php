<?php

namespace FlowEngine\Domain\Contracts;

interface ProjectConfig
{
    public function version(): string;
    public function rootPath(): string;

    public function scanInclude(): array;
    public function scanExclude(): array;
    public function scanExtensions(): array;

    public function contextType(): string;
    public function autoloadPath(): ?string;

    /** @return array<int, array{match:string, visibility:string}> */
    public function nodeVisibilityRules(): array;

    /** @return string public|hidden */
    public function defaultNodeVisibility(): string;

    /** @return array<string, string[]> Layer name => namespace prefixes */
    public function architectureLayers(): array;

    public function livewireNamespace(): string;

    /** @return string[] Custom class/method name patterns that are never orphans */
    public function entrypointPatterns(): array;

    /** @return int|null Max labeled snapshots to keep; null = unlimited */
    public function snapshotRetention(): ?int;

    /** @return array<string, mixed> Flutter-specific optional configuration */
    public function flutterConfig(): array;
}
