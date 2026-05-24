<?php

namespace FlowEngine\Infrastructure\Cache;

use FlowEngine\Domain\Contracts\ProjectContext;
use FlowEngine\Infrastructure\Paths\StateDirectory;

final class ReportCache
{
    private string $cacheDir;
    private string $reportsFile;
    private string $metaFile;

    public function __construct(ProjectContext $context)
    {
        $root = $context->rootPath();
        $stateDir = StateDirectory::forProjectRoot($root);
        $this->cacheDir = $stateDir . DIRECTORY_SEPARATOR . 'cache';
        $this->reportsFile = $this->cacheDir . DIRECTORY_SEPARATOR . 'reports.json.gz';
        $this->metaFile = $this->cacheDir . DIRECTORY_SEPARATOR . 'reports-meta.json';
    }

    public function isValid(string $hash): bool
    {
        if (!file_exists($this->reportsFile) || !file_exists($this->metaFile)) {
            return false;
        }

        $meta = json_decode((string) file_get_contents($this->metaFile), true);
        $cachedHash = $meta['hash'] ?? null;

        return $cachedHash === $hash;
    }

    /**
     * @return array<string, mixed>
     */
    public function load(): array
    {
        $raw = (string) file_get_contents($this->reportsFile);
        $json = gzdecode($raw);

        if ($json === false) {
            throw new \RuntimeException('Failed to decode reports cache');
        }

        $data = json_decode($json, true);

        if (!is_array($data)) {
            throw new \RuntimeException('Invalid reports cache JSON');
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $reports
     */
    public function save(array $reports, string $hash): void
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }

        $payload = json_encode($reports, JSON_THROW_ON_ERROR);
        $compressed = gzencode($payload, 6);

        file_put_contents($this->reportsFile, $compressed);
        file_put_contents(
            $this->metaFile,
            json_encode([
                'hash' => $hash,
                'generatedAt' => time(),
            ], JSON_PRETTY_PRINT)
        );
    }
}
