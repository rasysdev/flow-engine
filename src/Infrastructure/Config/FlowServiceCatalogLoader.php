<?php

namespace FlowEngine\Infrastructure\Config;

use FlowEngine\Application\AppMap\OpenApiContractParser;

final class FlowServiceCatalogLoader
{
    /**
     * @return array{
     *   catalogPath: string,
     *   baseDir: string,
     *   entries: array<int, array{
     *     path: string,
     *     name: string|null,
     *     hostnames: string[],
     *     contractEndpoints: array<int, array{method: string, path: string, summary: string}>|null,
     *     docker: array{
     *       composeFiles: string[],
     *       dockerfiles: string[],
     *       envFiles: string[],
     *       serviceNames: string[]
     *     }
     *   }>
     * }|null
     */
    public function load(string $catalogPath, ?string $projectPath = null): ?array
    {
        $full = $this->resolveCatalogPath($catalogPath, $projectPath);
        if ($full === null || !file_exists($full)) {
            return null;
        }

        $raw = json_decode((string) file_get_contents($full), true);
        if (!is_array($raw)) {
            return null;
        }

        $baseDir = dirname($full);
        $entries = [];

        foreach (($raw['services'] ?? []) as $svc) {
            if (!is_array($svc)) {
                continue;
            }

            $path = trim((string) ($svc['path'] ?? ''));
            if ($path === '') {
                continue;
            }

            $candidate = $this->resolvePath($path, $baseDir);
            $resolved = realpath($candidate) ?: $candidate;

            $hostnames = [];
            if (isset($svc['hostnames']) && is_array($svc['hostnames'])) {
                $hostnames = $this->stringArray($svc['hostnames']);
            }

            $contractEndpoints = null;
            if (isset($svc['contracts']) && is_array($svc['contracts'])) {
                $parser = new OpenApiContractParser();
                $contractEndpoints = [];
                foreach ($svc['contracts'] as $contractPath) {
                    if (!is_string($contractPath) || trim($contractPath) === '') {
                        continue;
                    }

                    $resolvedContract = $this->resolvePath($contractPath, $baseDir);
                    $contractEndpoints = array_merge($contractEndpoints, $parser->parse($resolvedContract));
                }
            }

            $docker = is_array($svc['docker'] ?? null) ? $svc['docker'] : [];

            $entries[] = [
                'path' => $resolved,
                'name' => isset($svc['name']) && is_string($svc['name']) ? $svc['name'] : null,
                'hostnames' => $hostnames,
                'contractEndpoints' => $contractEndpoints,
                'docker' => [
                    'composeFiles' => $this->resolvePaths($docker['composeFiles'] ?? [], $baseDir),
                    'dockerfiles' => $this->resolvePaths($docker['dockerfiles'] ?? [], $baseDir),
                    'envFiles' => $this->resolvePaths($docker['envFiles'] ?? [], $baseDir),
                    'serviceNames' => $this->stringArray($docker['serviceNames'] ?? []),
                ],
            ];
        }

        return [
            'catalogPath' => $full,
            'baseDir' => $baseDir,
            'entries' => $entries,
        ];
    }

    private function resolveCatalogPath(string $catalogPath, ?string $projectPath = null): ?string
    {
        $direct = realpath($catalogPath);
        if ($direct !== false) {
            return $direct;
        }

        if ($projectPath !== null) {
            $projectRelative = realpath($projectPath . DIRECTORY_SEPARATOR . $catalogPath);
            if ($projectRelative !== false) {
                return $projectRelative;
            }
        }

        return null;
    }

    private function resolvePath(string $path, string $baseDir): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1) {
            return $path;
        }

        return $baseDir . DIRECTORY_SEPARATOR . $path;
    }

    /**
     * @param mixed $value
     * @return string[]
     */
    private function stringArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                continue;
            }

            $trimmed = trim($item);
            if ($trimmed !== '') {
                $items[] = $trimmed;
            }
        }

        return array_values(array_unique($items));
    }

    /**
     * @param mixed $value
     * @return string[]
     */
    private function resolvePaths(mixed $value, string $baseDir): array
    {
        $paths = [];
        foreach ($this->stringArray($value) as $path) {
            $candidate = $this->resolvePath($path, $baseDir);
            $paths[] = realpath($candidate) ?: $candidate;
        }

        return array_values(array_unique($paths));
    }
}
