<?php

namespace FlowEngine\Infrastructure\Infra;

use FlowEngine\Application\InfraMap\Contract\ProjectSectionAnalyzer;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class FileInventoryAnalyzer implements ProjectSectionAnalyzer
{
    private const IGNORED_DIRECTORIES = [
        '.git',
        '.worktrees',
        '.cache',
        '.pytest_cache',
        '__pycache__',
        'node_modules',
        'vendor',
        'dados',
        'data',
        'storage',
        'tmp',
        'cache',
        'build',
        'dist',
        'coverage',
    ];

    /**
     * @return array<string, mixed>
     */
    public function analyze(string $projectRoot, string $mode = 'summary'): array
    {
        $root = realpath($projectRoot) ?: $projectRoot;
        if (!is_dir($root)) {
            return [
                'root' => $root,
                'topLevelDirectories' => [],
                'topLevelFiles' => [],
                'markers' => [],
                'relevantFiles' => [],
                'ignoredDirectories' => self::IGNORED_DIRECTORIES,
                'warnings' => ["Project root is not a directory: {$root}"],
            ];
        }

        $topLevelDirectories = [];
        $topLevelFiles = [];
        foreach (scandir($root) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $root . DIRECTORY_SEPARATOR . $entry;
            if (is_link($path)) {
                continue;
            }

            if (is_dir($path)) {
                if (!in_array($entry, self::IGNORED_DIRECTORIES, true)) {
                    $topLevelDirectories[] = $entry;
                }
                continue;
            }

            if (is_file($path)) {
                $topLevelFiles[] = $entry;
            }
        }

        sort($topLevelDirectories);
        sort($topLevelFiles);

        $markers = [];
        $relevantFiles = [];
        $limit = $mode === 'full' ? 500 : 120;

        foreach ($this->walkFiles($root) as $file) {
            $path = $file->getPathname();
            $relative = $this->relativePath($root, $path);
            $baseName = basename($path);

            foreach ($this->markersForFile($relative, $baseName) as $marker) {
                $markers[$marker] = true;
            }

            if (!$this->isRelevantFile($relative, $baseName)) {
                continue;
            }

            if (count($relevantFiles) < $limit) {
                $relevantFiles[] = [
                    'path' => $path,
                    'relativePath' => $relative,
                    'kind' => $this->fileKind($relative, $baseName),
                ];
            }
        }

        $markerList = array_keys($markers);
        sort($markerList);

        return [
            'root' => $root,
            'topLevelDirectories' => $topLevelDirectories,
            'topLevelFiles' => $topLevelFiles,
            'markers' => $markerList,
            'relevantFiles' => $relevantFiles,
            'ignoredDirectories' => self::IGNORED_DIRECTORIES,
            'warnings' => [],
        ];
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function walkFiles(string $root): iterable
    {
        $directory = new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS);
        $filter = new RecursiveCallbackFilterIterator(
            $directory,
            static function (SplFileInfo $current): bool {
                if ($current->isLink()) {
                    return false;
                }

                if (!$current->isDir()) {
                    return true;
                }

                return !in_array($current->getBasename(), self::IGNORED_DIRECTORIES, true);
            }
        );

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator($filter) as $file) {
            if ($file->isFile()) {
                yield $file;
            }
        }
    }

    private function isRelevantFile(string $relativePath, string $baseName): bool
    {
        $lowerBase = strtolower($baseName);
        $lowerPath = strtolower($relativePath);

        $exact = [
            '.htaccess',
            'caddyfile',
            'composer.json',
            'compose.yaml',
            'compose.yml',
            'docker-compose.yaml',
            'docker-compose.yml',
            'dockerfile',
            'flow-engine.json',
            'flow-services.json',
            'go.mod',
            'httpd.conf',
            'install.sh',
            'nginx.conf',
            'package.json',
            'pubspec.yaml',
            'pyproject.toml',
            'robots.txt',
            'sitemap.xml',
            'uninstall.sh',
            'update.sh',
        ];

        if (in_array($lowerBase, $exact, true)) {
            return true;
        }

        return preg_match('/(^|\/)dockerfile[._-].+/i', $relativePath) === 1
            || preg_match('/(^|\/)(docker-compose|compose).+\.ya?ml$/i', $relativePath) === 1
            || preg_match('/\.caddy$/i', $relativePath) === 1
            || preg_match('/(^|\/)(deploy|release|rebuild|setup|watch|validate).+\.sh$/i', $relativePath) === 1
            || preg_match('/\.(service|timer)$/i', $relativePath) === 1
            || str_contains($lowerPath, 'crontab');
    }

    /**
     * @return string[]
     */
    private function markersForFile(string $relativePath, string $baseName): array
    {
        $lowerBase = strtolower($baseName);
        $markers = [];

        if (str_starts_with($lowerBase, 'dockerfile') || preg_match('/^(docker-compose|compose).*\.ya?ml$/', $lowerBase) === 1) {
            $markers[] = 'docker';
        }
        if ($lowerBase === 'caddyfile' || str_ends_with($lowerBase, '.caddy') || $lowerBase === 'nginx.conf' || $lowerBase === 'httpd.conf') {
            $markers[] = 'proxy';
        }
        if ($lowerBase === 'robots.txt' || str_starts_with($lowerBase, 'sitemap')) {
            $markers[] = 'web-crawl';
        }
        if (str_ends_with($lowerBase, '.sh') || str_ends_with($lowerBase, '.service') || str_ends_with($lowerBase, '.timer')) {
            $markers[] = 'automation';
        }
        if (in_array($lowerBase, ['composer.json', 'package.json', 'pyproject.toml', 'go.mod', 'pubspec.yaml'], true)) {
            $markers[] = 'runtime-manifest';
        }
        if ($lowerBase === 'flow-engine.json' || $lowerBase === 'flow-services.json') {
            $markers[] = 'flow-engine';
        }

        return array_values(array_unique($markers));
    }

    private function fileKind(string $relativePath, string $baseName): string
    {
        $lowerBase = strtolower($baseName);

        if (str_starts_with($lowerBase, 'dockerfile')) {
            return 'dockerfile';
        }
        if (preg_match('/^(docker-compose|compose).*\.ya?ml$/', $lowerBase) === 1) {
            return 'compose';
        }
        if ($lowerBase === 'caddyfile' || str_ends_with($lowerBase, '.caddy')) {
            return 'caddy';
        }
        if ($lowerBase === 'nginx.conf' || $lowerBase === 'httpd.conf' || $lowerBase === '.htaccess') {
            return 'proxy-config';
        }
        if ($lowerBase === 'robots.txt') {
            return 'robots';
        }
        if (str_starts_with($lowerBase, 'sitemap')) {
            return 'sitemap';
        }
        if (str_ends_with($lowerBase, '.service') || str_ends_with($lowerBase, '.timer')) {
            return 'systemd';
        }
        if (str_ends_with($lowerBase, '.sh')) {
            return 'script';
        }

        return 'marker';
    }

    private function relativePath(string $root, string $path): string
    {
        $root = rtrim(str_replace('\\', '/', realpath($root) ?: $root), '/');
        $path = str_replace('\\', '/', realpath($path) ?: $path);

        if ($path === $root) {
            return '.';
        }

        if (str_starts_with($path, $root . '/')) {
            return substr($path, strlen($root) + 1);
        }

        return $path;
    }
}
