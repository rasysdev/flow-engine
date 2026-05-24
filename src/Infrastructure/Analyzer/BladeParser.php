<?php

namespace FlowEngine\Infrastructure\Analyzer;

use FlowEngine\Domain\Flow\Edge;

/**
 * Parses .blade.php files to extract Livewire wire:click/wire:submit edges.
 *
 * Generates edges from blade views to Livewire component methods:
 *   blade:livewire.backup.b2-manager -> App\Http\Livewire\Backup\B2Manager::methodName
 *
 * Does not create nodes (only edges).
 *
 * @internal
 */
final class BladeParser implements FileParser
{
    private const WIRE_ACTION_PATTERN = '/wire:(click|submit\.prevent|submit|keydown\.enter|change)\s*=\s*["\']([a-zA-Z_]\w*(?:\([^)]*\))?)["\']/';
    public function __construct(
        private readonly string $projectRoot,
        private readonly string $livewireNamespace = 'App\\Http\\Livewire'
    ) {
    }

    /**
     * @return array{nodes: array<empty>, edges: Edge[]}
     */
    public function parse(string $file): array
    {
        $content = @file_get_contents($file);
        if ($content === false) {
            return ['nodes' => [], 'edges' => []];
        }

        // Only process Livewire blade views
        $componentFqn = $this->resolveComponentFqn($file);
        if ($componentFqn === null) {
            return ['nodes' => [], 'edges' => []];
        }

        $bladeId = $this->resolveBladeId($file);
        $edges = [];
        $seenMethods = [];

        if (preg_match_all(self::WIRE_ACTION_PATTERN, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $methodCall = $m[2];
                // Strip arguments: "save()" -> "save", "delete($id)" -> "delete"
                $methodName = preg_replace('/\(.*\)/', '', $methodCall);
                if ($methodName === null || $methodName === '') {
                    continue;
                }

                // Deduplicate within same file
                $key = $componentFqn . '::' . $methodName;
                if (isset($seenMethods[$key])) {
                    continue;
                }
                $seenMethods[$key] = true;

                $edges[] = new Edge(
                    $bladeId,
                    $componentFqn . '::' . $methodName,
                    $methodName,
                    'wire_action'
                );
            }
        }

        return ['nodes' => [], 'edges' => $edges];
    }

    /**
     * Resolve Livewire component FQN from blade file path.
     *
     * resources/views/livewire/backup/b2-manager.blade.php
     * -> App\Http\Livewire\Backup\B2Manager
     */
    private function resolveComponentFqn(string $file): ?string
    {
        $root = rtrim($this->projectRoot, DIRECTORY_SEPARATOR);
        $normalized = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $file);

        $livewirePath = $root . DIRECTORY_SEPARATOR . 'resources'
            . DIRECTORY_SEPARATOR . 'views'
            . DIRECTORY_SEPARATOR . 'livewire'
            . DIRECTORY_SEPARATOR;

        if (!str_starts_with($normalized, $livewirePath)) {
            return null;
        }

        $relative = substr($normalized, strlen($livewirePath));
        // Remove .blade.php extension
        $relative = preg_replace('/\.blade\.php$/i', '', $relative);
        if ($relative === null || $relative === '') {
            return null;
        }

        // Convert path segments to PascalCase
        $segments = explode(DIRECTORY_SEPARATOR, $relative);
        $pascalSegments = array_map(
            fn(string $s) => $this->kebabToPascal($s),
            $segments
        );

        return $this->livewireNamespace . '\\' . implode('\\', $pascalSegments);
    }

    /**
     * Generate blade identifier for edge source.
     *
     * resources/views/livewire/backup/b2-manager.blade.php
     * -> blade:livewire.backup.b2-manager
     */
    private function resolveBladeId(string $file): string
    {
        $root = rtrim($this->projectRoot, DIRECTORY_SEPARATOR);
        $normalized = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $file);

        $viewsPath = $root . DIRECTORY_SEPARATOR . 'resources'
            . DIRECTORY_SEPARATOR . 'views'
            . DIRECTORY_SEPARATOR;

        $relative = $file;
        if (str_starts_with($normalized, $viewsPath)) {
            $relative = substr($normalized, strlen($viewsPath));
        }

        // Remove .blade.php extension
        $relative = preg_replace('/\.blade\.php$/i', '', $relative) ?? $relative;
        // Convert path separators to dots
        $relative = str_replace([DIRECTORY_SEPARATOR, '\\', '/'], '.', $relative);

        return 'blade:' . $relative;
    }

    /**
     * Convert kebab-case to PascalCase.
     *
     * b2-manager -> B2Manager
     * backup -> Backup
     */
    private function kebabToPascal(string $kebab): string
    {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $kebab)));
    }
}
