<?php

namespace FlowEngine\Infrastructure\Infra;

use FlowEngine\Application\InfraMap\Contract\ProjectSectionAnalyzer;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ScriptTopologyAnalyzer implements ProjectSectionAnalyzer
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
        $scripts = [];
        $systemdUnits = [];
        $edges = [];
        $warnings = [];

        foreach ($this->findAutomationFiles($root) as $file) {
            $baseName = strtolower(basename($file));
            if (str_ends_with($baseName, '.service') || str_ends_with($baseName, '.timer')) {
                $unit = $this->parseSystemdUnit($root, $file);
                $systemdUnits[] = $unit;
                continue;
            }

            $parsed = $this->parseScript($root, $file);
            if ($parsed === null) {
                $warnings[] = "Cannot read script: {$file}";
                continue;
            }
            $scripts[] = $parsed['script'];
            $edges = array_merge($edges, $parsed['edges']);
        }

        if ($mode === 'summary') {
            $scripts = array_slice($scripts, 0, 80);
            $systemdUnits = array_slice($systemdUnits, 0, 80);
            $edges = array_slice($edges, 0, 160);
        }

        return [
            'scripts' => $scripts,
            'systemdUnits' => $systemdUnits,
            'summary' => [
                'scriptCount' => count($scripts),
                'systemdUnitCount' => count($systemdUnits),
            ],
            'edges' => $edges,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /**
     * @return string[]
     */
    private function findAutomationFiles(string $root): array
    {
        if (!is_dir($root)) {
            return [];
        }

        $files = [];
        foreach ($this->walkFiles($root) as $file) {
            $baseName = strtolower($file->getBasename());
            if (str_ends_with($baseName, '.sh')
                || str_ends_with($baseName, '.service')
                || str_ends_with($baseName, '.timer')
                || str_contains($baseName, 'crontab')
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
     * @return array{script: array<string, mixed>, edges: array<int, array<string, mixed>>}|null
     */
    private function parseScript(string $root, string $file): ?array
    {
        $content = @file_get_contents($file);
        if ($content === false) {
            return null;
        }

        $relative = $this->relativePath($root, $file);
        $composeFiles = $this->extractComposeReferences($root, dirname($file), $content);
        $systemctlTargets = $this->extractSystemctlTargets($content);
        $dockerCompose = str_contains($content, 'docker compose');
        $edges = [];
        $scriptNode = 'script:' . $relative;

        foreach ($composeFiles as $composeFile) {
            $edges[] = [
                'from' => $scriptNode,
                'to' => 'compose:' . $composeFile,
                'type' => 'references_compose',
                'source' => $file,
            ];
        }

        foreach ($systemctlTargets as $target) {
            $edges[] = [
                'from' => $scriptNode,
                'to' => 'systemd:' . $target,
                'type' => 'controls_systemd_unit',
                'source' => $file,
            ];
        }

        return [
            'script' => [
                'path' => $file,
                'relativePath' => $relative,
                'kind' => $this->scriptKind(basename($file)),
                'usesDockerCompose' => $dockerCompose,
                'composeFiles' => $composeFiles,
                'systemctlTargets' => $systemctlTargets,
            ],
            'edges' => $edges,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseSystemdUnit(string $root, string $file): array
    {
        $content = @file_get_contents($file) ?: '';
        $execStart = null;
        $onCalendar = null;

        if (preg_match('/^ExecStart=(.+)$/mi', $content, $matches) === 1) {
            $execStart = trim($matches[1]);
        }
        if (preg_match('/^OnCalendar=(.+)$/mi', $content, $matches) === 1) {
            $onCalendar = trim($matches[1]);
        }

        return [
            'path' => $file,
            'relativePath' => $this->relativePath($root, $file),
            'name' => basename($file),
            'kind' => str_ends_with(strtolower($file), '.timer') ? 'timer' : 'service',
            'execStart' => $execStart,
            'onCalendar' => $onCalendar,
        ];
    }

    /**
     * @return string[]
     */
    private function extractComposeReferences(string $root, string $scriptDir, string $content): array
    {
        $references = [];
        if (preg_match_all('/(?:^|\s)-f\s+([^\s"\']+\.ya?ml)/i', $content, $matches) > 0) {
            foreach ($matches[1] as $match) {
                $references[] = $this->resolveRelativePath($root, $scriptDir, $match);
            }
        }

        if (preg_match_all('/(?:^|[\s"\'])([^\s"\']*(?:docker-compose|compose)[^\s"\']*\.ya?ml)(?=$|[\s"\'])/i', $content, $matches) > 0) {
            foreach ($matches[1] as $match) {
                $references[] = $this->resolveRelativePath($root, $scriptDir, $match);
            }
        }

        return array_values(array_unique(array_filter($references)));
    }

    /**
     * @return string[]
     */
    private function extractSystemctlTargets(string $content): array
    {
        $targets = [];
        if (preg_match_all('/\bsystemctl\s+(?:--user\s+)?(?:enable|restart|start|stop|reload|status)\s+(?:--now\s+)?([A-Za-z0-9_.@-]+)/', $content, $matches) > 0) {
            foreach ($matches[1] as $target) {
                $targets[] = trim($target);
            }
        }

        return array_values(array_unique(array_filter($targets)));
    }

    private function scriptKind(string $baseName): string
    {
        $lower = strtolower($baseName);
        return match (true) {
            $lower === 'install.sh' => 'install',
            $lower === 'update.sh' => 'update',
            $lower === 'uninstall.sh' => 'uninstall',
            str_starts_with($lower, 'deploy') => 'deploy',
            str_starts_with($lower, 'release') => 'release',
            str_contains($lower, 'crontab') => 'cron',
            default => 'shell',
        };
    }

    private function resolveRelativePath(string $root, string $baseDir, string $path): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1) {
            return realpath($path) ?: $path;
        }

        $candidate = $baseDir . DIRECTORY_SEPARATOR . $path;
        $resolved = realpath($candidate) ?: $candidate;

        return $this->relativePath($root, $resolved);
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
