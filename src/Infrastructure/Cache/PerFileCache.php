<?php

namespace FlowEngine\Infrastructure\Cache;

use FlowEngine\Domain\Contracts\ProjectContext;
use FlowEngine\Infrastructure\Paths\StateDirectory;
use RuntimeException;

/**
 * Per-file incremental cache for parser output.
 *
 * Storage: <stateDir>/cache/per-file.json.gz
 *
 * Each entry is keyed by absolute file path and stores:
 *   - fp: fingerprint string ("path|mtime|size" or "path|missing")
 *   - nodes: raw serialized node arrays (pre-visibility)
 *   - edges: raw serialized edge arrays
 *
 * Nodes are stored pre-visibility so FlowBuilder always re-applies the policy.
 */
final class PerFileCache
{
    private string $cacheFile;

    public function __construct(ProjectContext $context)
    {
        $stateDir = StateDirectory::forProjectRoot($context->rootPath());
        $this->cacheFile = $stateDir . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'per-file.json.gz';
    }

    /**
     * Returns a fingerprint string for the given file path.
     * Format: "path|mtime|size" or "path|missing" if the file does not exist.
     */
    public static function fingerprint(string $path): string
    {
        if (!file_exists($path)) {
            return $path . '|missing';
        }

        return $path . '|' . filemtime($path) . '|' . filesize($path);
    }

    /**
     * Load the per-file cache map.
     *
     * @return array<string, array{fp: string, nodes: array, edges: array}>
     */
    public function load(): array
    {
        if (!file_exists($this->cacheFile)) {
            return [];
        }

        $compressed = file_get_contents($this->cacheFile);

        if ($compressed === false) {
            return [];
        }

        $json = gzdecode($compressed);

        if ($json === false) {
            return [];
        }

        $data = json_decode($json, true);

        return is_array($data) ? $data : [];
    }

    /**
     * Persist the per-file cache map.
     *
     * @param array<string, array{fp: string, nodes: array, edges: array}> $results
     */
    public function save(array $results): void
    {
        $dir = dirname($this->cacheFile);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $json = json_encode($results, JSON_THROW_ON_ERROR);
        $compressed = gzencode($json, 6);

        if ($compressed === false) {
            throw new RuntimeException('Failed to compress per-file cache');
        }

        file_put_contents($this->cacheFile, $compressed);
    }
}
