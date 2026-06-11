<?php

namespace FlowEngine\Infrastructure\Infra;

use FlowEngine\Application\InfraMap\Contract\ProjectSectionAnalyzer;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class CaddyTopologyAnalyzer implements ProjectSectionAnalyzer
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
        $files = $this->findProxyFiles($root);
        $warnings = [];
        $caddySites = [];
        $caddySnippets = [];
        $generic = [];
        $edges = [];

        foreach ($files as $file) {
            $baseName = strtolower(basename($file));
            if ($baseName === 'caddyfile' || str_ends_with($baseName, '.caddy')) {
                $parsed = $this->parseCaddyFile($root, $file);
                $caddySites = array_merge($caddySites, $parsed['sites']);
                $caddySnippets = array_merge($caddySnippets, $parsed['snippets']);
                $edges = array_merge($edges, $parsed['edges']);
                $warnings = array_merge($warnings, $parsed['warnings']);
                continue;
            }

            $parsed = $this->parseGenericProxyFile($root, $file);
            if ($parsed !== null) {
                $generic[] = $parsed['config'];
                $edges = array_merge($edges, $parsed['edges']);
            }
        }

        if ($mode === 'summary') {
            $caddySites = array_slice($caddySites, 0, 50);
            $caddySnippets = array_slice($caddySnippets, 0, 50);
            $generic = array_slice($generic, 0, 50);
            $edges = array_slice($edges, 0, 150);
        }

        return [
            'files' => $files,
            'caddy' => [
                'sites' => $caddySites,
                'snippets' => $caddySnippets,
            ],
            'generic' => $generic,
            'summary' => [
                'fileCount' => count($files),
                'caddySiteCount' => count($caddySites),
                'genericConfigCount' => count($generic),
            ],
            'edges' => $edges,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /**
     * @return string[]
     */
    private function findProxyFiles(string $root): array
    {
        if (!is_dir($root)) {
            return [];
        }

        $files = [];
        foreach ($this->walkFiles($root) as $file) {
            $baseName = strtolower($file->getBasename());
            $relative = strtolower($this->relativePath($root, $file->getPathname()));

            if ($baseName === 'caddyfile'
                || str_ends_with($baseName, '.caddy')
                || $baseName === 'nginx.conf'
                || $baseName === 'httpd.conf'
                || $baseName === '.htaccess'
                || str_ends_with($relative, '.vhost')
            ) {
                $files[] = realpath($file->getPathname()) ?: $file->getPathname();
            }
        }

        sort($files);
        return array_values(array_unique($files));
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

    /**
     * @return array{sites: array<int, array<string, mixed>>, snippets: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>, warnings: string[]}
     */
    private function parseCaddyFile(string $root, string $file): array
    {
        $lines = @file($file, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return [
                'sites' => [],
                'snippets' => [],
                'edges' => [],
                'warnings' => ["Cannot read proxy config: {$file}"],
            ];
        }

        $sites = [];
        $snippets = [];
        $edges = [];
        $currentSite = null;
        $currentSnippet = null;
        $depth = 0;
        $relativeFile = $this->relativePath($root, $file);

        foreach ($lines as $lineNumber => $line) {
            $trimmed = trim($this->stripComment($line));
            if ($trimmed === '') {
                continue;
            }

            if ($currentSite === null && $currentSnippet === null && preg_match('/^\(([^)]+)\)\s*\{$/', $trimmed, $matches) === 1) {
                $currentSnippet = [
                    'name' => $matches[1],
                    'file' => $file,
                    'relativePath' => $relativeFile,
                    'line' => $lineNumber + 1,
                ];
                $depth = 1;
                continue;
            }

            if ($currentSite === null && $currentSnippet === null && str_ends_with($trimmed, '{')) {
                $hostExpression = trim(substr($trimmed, 0, -1));
                if ($hostExpression !== '' && !$this->isCaddyDirective($hostExpression)) {
                    $hosts = $this->splitHosts($hostExpression);
                    $currentSite = [
                        'file' => $file,
                        'relativePath' => $relativeFile,
                        'line' => $lineNumber + 1,
                        'hosts' => $hosts,
                        'reverseProxies' => [],
                        'imports' => [],
                        'blocksRequests' => false,
                        'basicAuth' => false,
                        'tls' => [],
                    ];
                    $depth = 1;
                    continue;
                }
            }

            if ($currentSite !== null) {
                if (preg_match('/^reverse_proxy\s+(.+?)(?:\s*\{)?$/', $trimmed, $matches) === 1) {
                    foreach ($this->tokensBeforeBlock($matches[1]) as $upstream) {
                        $currentSite['reverseProxies'][] = $upstream;
                        foreach ($currentSite['hosts'] as $host) {
                            $edges[] = [
                                'from' => 'host:' . $host,
                                'to' => 'upstream:' . $upstream,
                                'type' => 'reverse_proxy',
                                'source' => $file,
                            ];
                        }
                    }
                } elseif (preg_match('/^import\s+(.+)$/', $trimmed, $matches) === 1) {
                    $import = trim($matches[1]);
                    $currentSite['imports'][] = $import;
                    foreach ($currentSite['hosts'] as $host) {
                        $edges[] = [
                            'from' => 'host:' . $host,
                            'to' => 'caddy-import:' . $import,
                            'type' => 'imports',
                            'source' => $file,
                        ];
                    }
                } elseif (preg_match('/^respond\b.*\b444\b/', $trimmed) === 1) {
                    $currentSite['blocksRequests'] = true;
                } elseif (str_starts_with($trimmed, 'basic_auth')) {
                    $currentSite['basicAuth'] = true;
                } elseif (preg_match('/^tls\b(.*)$/', $trimmed, $matches) === 1) {
                    $tls = trim($matches[1]);
                    if ($tls !== '') {
                        $currentSite['tls'][] = $tls;
                    }
                }
            }

            $depth += substr_count($trimmed, '{') - substr_count($trimmed, '}');
            if ($depth <= 0) {
                if ($currentSite !== null) {
                    $currentSite['reverseProxies'] = array_values(array_unique($currentSite['reverseProxies']));
                    $currentSite['imports'] = array_values(array_unique($currentSite['imports']));
                    $currentSite['tls'] = array_values(array_unique($currentSite['tls']));
                    $sites[] = $currentSite;
                    $currentSite = null;
                }
                if ($currentSnippet !== null) {
                    $snippets[] = $currentSnippet;
                    $currentSnippet = null;
                }
                $depth = 0;
            }
        }

        return [
            'sites' => $sites,
            'snippets' => $snippets,
            'edges' => $edges,
            'warnings' => [],
        ];
    }

    /**
     * @return array{config: array<string, mixed>, edges: array<int, array<string, mixed>>}|null
     */
    private function parseGenericProxyFile(string $root, string $file): ?array
    {
        $content = @file_get_contents($file);
        if ($content === false) {
            return null;
        }

        $hosts = [];
        $upstreams = [];

        if (preg_match_all('/\bserver_name\s+([^;]+);/i', $content, $matches) > 0) {
            foreach ($matches[1] as $match) {
                foreach (preg_split('/\s+/', trim($match)) ?: [] as $host) {
                    if ($host !== '') {
                        $hosts[] = $host;
                    }
                }
            }
        }

        if (preg_match_all('/\bServer(?:Name|Alias)\s+([^\s]+)/i', $content, $matches) > 0) {
            foreach ($matches[1] as $host) {
                $hosts[] = trim($host);
            }
        }

        if (preg_match_all('/\bproxy_pass\s+([^;\s]+)/i', $content, $matches) > 0) {
            foreach ($matches[1] as $upstream) {
                $upstreams[] = trim($upstream);
            }
        }

        if (preg_match_all('/\bProxyPass\s+\S+\s+([^\s]+)/i', $content, $matches) > 0) {
            foreach ($matches[1] as $upstream) {
                $upstreams[] = trim($upstream);
            }
        }

        $hosts = array_values(array_unique(array_filter($hosts)));
        $upstreams = array_values(array_unique(array_filter($upstreams)));
        $edges = [];
        foreach ($hosts as $host) {
            foreach ($upstreams as $upstream) {
                $edges[] = [
                    'from' => 'host:' . $host,
                    'to' => 'upstream:' . $upstream,
                    'type' => 'reverse_proxy',
                    'source' => $file,
                ];
            }
        }

        return [
            'config' => [
                'file' => $file,
                'relativePath' => $this->relativePath($root, $file),
                'kind' => $this->genericKind($file),
                'hosts' => $hosts,
                'upstreams' => $upstreams,
            ],
            'edges' => $edges,
        ];
    }

    private function genericKind(string $file): string
    {
        $baseName = strtolower(basename($file));
        if ($baseName === 'nginx.conf') {
            return 'nginx';
        }
        if ($baseName === 'httpd.conf' || $baseName === '.htaccess') {
            return 'apache';
        }

        return 'proxy-config';
    }

    private function stripComment(string $line): string
    {
        return rtrim((string) preg_replace('/\s+#.*$/', '', $line));
    }

    /**
     * @return string[]
     */
    private function splitHosts(string $hostExpression): array
    {
        $hosts = [];
        foreach (preg_split('/\s*,\s*/', $hostExpression) ?: [] as $host) {
            $trimmed = trim($host);
            if ($trimmed !== '') {
                $hosts[] = $trimmed;
            }
        }

        return array_values(array_unique($hosts));
    }

    private function isCaddyDirective(string $value): bool
    {
        return preg_match('/^(handle|handle_path|route|respond|reverse_proxy|header|log|tls|basic_auth|redir|encode)\b/', $value) === 1;
    }

    /**
     * @return string[]
     */
    private function tokensBeforeBlock(string $value): array
    {
        $clean = trim(str_replace('{', ' ', $value));
        $tokens = [];
        foreach (preg_split('/\s+/', $clean) ?: [] as $token) {
            $token = trim($token);
            if ($token !== '') {
                $tokens[] = $token;
            }
        }

        return array_values(array_unique($tokens));
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
