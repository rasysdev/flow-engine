<?php

namespace FlowEngine\Domain\Flow\Query\Filters;

use FlowEngine\Domain\Flow\NodeCollection;

/**
 * Filtra nodes por namespace.
 * 
 * Exemplos:
 * - "App\Services" → App\Services\UserService, App\Services\Auth\LoginService
 * - "App\Services\Auth" → App\Services\Auth\LoginService (mais específico)
 */
final class NamespaceFilter
{
    public function __construct(
        private string $namespace
    ) {
    }

    /**
     * @internal 
     */
    public function apply(NodeCollection $nodes): NodeCollection
    {
        $filtered = [];

        foreach ($nodes->all() as $node) {
            if ($this->matchesNamespace($node->class())) {
                $filtered[] = $node;
            }
        }

        return new NodeCollection($filtered);
    }

    /**
     * @internal 
     */
    private function matchesNamespace(string $class): bool
    {
        // Remove trailing backslash
        $namespace = rtrim($this->namespace, '\\');

        // Check if class starts with namespace
        return str_starts_with($class, $namespace . '\\')
            || $class === $namespace;
    }
}