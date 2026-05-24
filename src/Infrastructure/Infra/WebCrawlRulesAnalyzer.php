<?php

namespace FlowEngine\Infrastructure\Infra;

use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class WebCrawlRulesAnalyzer
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
        $robots = [];
        $sitemaps = [];
        $metaRobots = [];
        $canonicals = [];
        $edges = [];
        $warnings = [];

        foreach ($this->walkFiles($root) as $file) {
            $path = $file->getPathname();
            $baseName = strtolower($file->getBasename());
            $relative = $this->relativePath($root, $path);

            if ($baseName === 'robots.txt') {
                $parsed = $this->parseRobots($root, $path);
                $robots[] = $parsed;
                foreach ($parsed['sitemaps'] as $sitemap) {
                    $edges[] = [
                        'from' => 'robots:' . $relative,
                        'to' => 'sitemap:' . $sitemap,
                        'type' => 'declares_sitemap',
                        'source' => $path,
                    ];
                }
                continue;
            }

            if (str_starts_with($baseName, 'sitemap') && preg_match('/\.xml(?:\.gz)?$/', $baseName) === 1) {
                $sitemaps[] = [
                    'path' => $path,
                    'relativePath' => $relative,
                ];
                continue;
            }

            if (!$this->isMarkupFile($baseName)) {
                continue;
            }

            if ($file->getSize() > 262144) {
                continue;
            }

            $content = @file_get_contents($path);
            if ($content === false) {
                $warnings[] = "Cannot read web file: {$path}";
                continue;
            }

            foreach ($this->extractMetaRobots($content) as $contentValue) {
                $metaRobots[] = [
                    'path' => $path,
                    'relativePath' => $relative,
                    'content' => $contentValue,
                ];
            }

            foreach ($this->extractCanonicals($content) as $href) {
                $canonicals[] = [
                    'path' => $path,
                    'relativePath' => $relative,
                    'href' => $href,
                ];
            }
        }

        if ($mode === 'summary') {
            $robots = array_slice($robots, 0, 30);
            $sitemaps = array_slice($sitemaps, 0, 50);
            $metaRobots = array_slice($metaRobots, 0, 50);
            $canonicals = array_slice($canonicals, 0, 50);
            $edges = array_slice($edges, 0, 120);
        }

        return [
            'robots' => $robots,
            'sitemaps' => $sitemaps,
            'metaRobots' => $metaRobots,
            'canonicals' => $canonicals,
            'summary' => [
                'robotsCount' => count($robots),
                'sitemapCount' => count($sitemaps),
                'metaRobotsCount' => count($metaRobots),
                'canonicalCount' => count($canonicals),
            ],
            'edges' => $edges,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function walkFiles(string $root): iterable
    {
        if (!is_dir($root)) {
            return;
        }

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

    /**
     * @return array<string, mixed>
     */
    private function parseRobots(string $root, string $path): array
    {
        $lines = @file($path, FILE_IGNORE_NEW_LINES) ?: [];
        $userAgents = [];
        $allow = [];
        $disallow = [];
        $sitemaps = [];

        foreach ($lines as $line) {
            $line = trim((string) preg_replace('/\s+#.*$/', '', $line));
            if ($line === '' || !str_contains($line, ':')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode(':', $line, 2));
            $key = strtolower($key);
            if ($key === 'user-agent') {
                $userAgents[] = $value;
            } elseif ($key === 'allow') {
                $allow[] = $value;
            } elseif ($key === 'disallow') {
                $disallow[] = $value;
            } elseif ($key === 'sitemap') {
                $sitemaps[] = $value;
            }
        }

        return [
            'path' => $path,
            'relativePath' => $this->relativePath($root, $path),
            'userAgents' => array_values(array_unique($userAgents)),
            'allow' => array_values(array_unique($allow)),
            'disallow' => array_values(array_unique($disallow)),
            'sitemaps' => array_values(array_unique($sitemaps)),
        ];
    }

    private function isMarkupFile(string $baseName): bool
    {
        return preg_match('/\.(html?|php|blade\.php|tsx|jsx|vue|astro)$/i', $baseName) === 1;
    }

    /**
     * @return string[]
     */
    private function extractMetaRobots(string $content): array
    {
        $values = [];
        if (preg_match_all('/<meta\b(?=[^>]*\bname=["\']robots["\'])(?=[^>]*\bcontent=["\']([^"\']+)["\'])[^>]*>/i', $content, $matches) > 0) {
            foreach ($matches[1] as $match) {
                $values[] = trim($match);
            }
        }

        return array_values(array_unique(array_filter($values)));
    }

    /**
     * @return string[]
     */
    private function extractCanonicals(string $content): array
    {
        $values = [];
        if (preg_match_all('/<link\b(?=[^>]*\brel=["\']canonical["\'])(?=[^>]*\bhref=["\']([^"\']+)["\'])[^>]*>/i', $content, $matches) > 0) {
            foreach ($matches[1] as $match) {
                $values[] = trim($match);
            }
        }

        return array_values(array_unique(array_filter($values)));
    }

    private function relativePath(string $root, string $path): string
    {
        $root = rtrim(str_replace('\\', '/', realpath($root) ?: $root), '/');
        $path = str_replace('\\', '/', realpath($path) ?: $path);

        if (str_starts_with($path, $root . '/')) {
            return substr($path, strlen($root) + 1);
        }

        return $path;
    }
}
