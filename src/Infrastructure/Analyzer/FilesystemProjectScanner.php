<?php

namespace FlowEngine\Infrastructure\Analyzer;

use FlowEngine\Domain\Contracts\ProjectContext;

final class FilesystemProjectScanner implements ProjectScanner
{
    /**
     * Directories that should never be scanned regardless of project context.
     * These contain third-party code, build artifacts, or VCS internals that
     * are never part of the analysable project source.
     */
    private const DEFAULT_IGNORED_DIRS = [
        // Package managers
        'node_modules',
        'vendor',
        'bower_components',
        'jspm_packages',
        'web_modules',
        // VCS
        '.git',
        '.svn',
        '.hg',
        // Build outputs
        'dist',
        'build',
        'target',
        'out',
        '_build',
        // Coverage
        'coverage',
        '.nyc_output',
        // Caches
        '.cache',
        '.turbo',
        '.next',
        '.nuxt',
        '.output',
        '.svelte-kit',
        '.astro',
        '.parcel-cache',
        '.vite',
        '__pycache__',
        '.pytest_cache',
        '.mypy_cache',
        '.ruff_cache',
        '.tox',
        // Package manager caches
        '.npm',
        '.yarn',
        '.pnpm-store',
        '.pub-cache',
        // Dart/Flutter
        '.dart_tool',
        // Ruby
        '.bundle',
        // iOS
        'ios/Pods',
        'Pods',
        'Carthage',
        // Deploy/hosting
        '.vercel',
        '.netlify',
        '.firebase',
        // IDE
        '.idea',
        '.vscode',
        // Virtualenv
        '.venv',
        'venv',
        // Vendored / misc
        'third_party',
        'third-party',
        'vendored',
    ];

    /**
     * @internal
     */
    public function scan(ProjectContext $context): array
    {
        $root = $context->rootPath();
        $includes = $context->includePaths();
        $ignored = array_values(array_unique(array_merge(
            self::DEFAULT_IGNORED_DIRS,
            $context->ignoredPaths(),
        )));
        $extensions = array_map('strtolower', $context->extensions());

        if ($includes === []) {
            $includes = ['.'];
        }

        $files = [];

        foreach ($includes as $include) {
            $include = trim((string) $include);
            if ($include === '') {
                continue;
            }

            $base = $include === '.'
                ? $root
                : $root . DIRECTORY_SEPARATOR . $include;

            if (!is_dir($base)) {
                continue;
            }

            if (is_file($base . DIRECTORY_SEPARATOR . 'pyvenv.cfg')) {
                continue;
            }

            $directoryIterator = new \RecursiveDirectoryIterator(
                $base,
                \FilesystemIterator::SKIP_DOTS
            );

            // Prune ignored directories during traversal so node_modules/vendor/etc.
            // are never descended into — avoids O(n) walks over dependency trees.
            $prunedIterator = new \RecursiveCallbackFilterIterator(
                $directoryIterator,
                function ($current, $key, $innerIterator) use ($ignored): bool {
                    if (!$current->isDir()) {
                        return true;
                    }
                    if (is_file($current->getPathname() . DIRECTORY_SEPARATOR . 'pyvenv.cfg')) {
                        return false;
                    }
                    $subPath = $innerIterator->getSubPathname();
                    return !$this->isPathIgnored($subPath, $ignored);
                }
            );

            $iterator = new \RecursiveIteratorIterator(
                $prunedIterator,
                \RecursiveIteratorIterator::LEAVES_ONLY,
                \RecursiveIteratorIterator::CATCH_GET_CHILD
            );

            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                $ext = strtolower((string) $file->getExtension());
                if (!in_array($ext, $extensions, true)) {
                    continue;
                }

                $relative = str_replace($root . DIRECTORY_SEPARATOR, '', $file->getPathname());

                if ($this->isPathIgnored($relative, $ignored)) {
                    continue;
                }

                $files[] = $file->getPathname();
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * @param list<string> $ignored
     */
    private function isPathIgnored(string $relativePath, array $ignored): bool
    {
        $relativePath = str_replace('\\', '/', $relativePath);
        foreach ($ignored as $dir) {
            $dir = str_replace('\\', '/', rtrim((string) $dir, "/\\"));
            if ($dir === '') {
                continue;
            }
            if (
                $relativePath === $dir
                || str_starts_with($relativePath, $dir . '/')
                || str_contains($relativePath, '/' . $dir . '/')
                || str_ends_with($relativePath, '/' . $dir)
            ) {
                return true;
            }
        }
        return false;
    }
}
