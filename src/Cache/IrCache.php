<?php

namespace FlowEngine\Cache;

use FlowEngine\Domain\Contracts\ProjectContext;

final class IrCache
{
    private string $cacheDir;
    private string $cacheFile;
    private string $metaFile;

    public function __construct(ProjectContext $context)
    {
        $root = $context->rootPath();

        $this->cacheDir  = $root . DIRECTORY_SEPARATOR . '.flow-engine';
        $this->cacheFile = $this->cacheDir . DIRECTORY_SEPARATOR . 'ir.json';
        $this->metaFile  = $this->cacheDir . DIRECTORY_SEPARATOR . 'meta.json';
    }

    /**
     * @param string[] $files
     */
    public function isValid(array $files): bool
    {
        if (!file_exists($this->cacheFile) || !file_exists($this->metaFile)) {
            return false;
        }

        $meta = json_decode(
            file_get_contents($this->metaFile),
            true
        );

        $lastModified = $meta['lastModified'] ?? 0;

        foreach ($files as $file) {
            if (!file_exists($file) || filemtime($file) > $lastModified) {
                return false;
            }
        }

        return true;
    }

    public function load(): array
    {
        return json_decode(
            file_get_contents($this->cacheFile),
            true
        );
    }

    /**
     * @param array    $ir
     * @param string[] $files
     */
    public function save(array $ir, array $files): void
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }

        $lastModified = 0;

        foreach ($files as $file) {
            if (file_exists($file)) {
                $lastModified = max(
                    $lastModified,
                    filemtime($file)
                );
            }
        }

        file_put_contents(
            $this->cacheFile,
            json_encode($ir, JSON_PRETTY_PRINT)
        );

        file_put_contents(
            $this->metaFile,
            json_encode([
                'lastModified' => $lastModified,
                'generatedAt'  => time(),
            ], JSON_PRETTY_PRINT)
        );
    }
}
