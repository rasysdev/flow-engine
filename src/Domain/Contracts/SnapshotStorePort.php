<?php

namespace FlowEngine\Domain\Contracts;

interface SnapshotStorePort
{
    public function save(string $label, array $reports): void;

    public function load(string $label): array;

    public function exists(string $label): bool;
}
