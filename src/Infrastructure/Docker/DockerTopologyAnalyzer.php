<?php

namespace FlowEngine\Infrastructure\Docker;

use FlowEngine\Application\InfraMap\Contract\DockerTopologyReader;

final class DockerTopologyAnalyzer implements DockerTopologyReader
{
    public function __construct(
        private ?SimpleYamlParser $yamlParser = null,
    ) {
        $this->yamlParser ??= new SimpleYamlParser();
    }

    /**
     * @param array<int, array{
     *   path: string,
     *   name: string|null,
     *   hostnames: string[],
     *   contractEndpoints: array<int, array{method: string, path: string, summary: string}>|null,
     *   docker: array{
     *     composeFiles: string[],
     *     dockerfiles: string[],
     *     envFiles: string[],
     *     serviceNames: string[]
     *   }
     * }> $entries
     * @return array{
     *   detectedComposeFiles: string[],
     *   dockerfiles: string[],
     *   environmentFiles: string[],
     *   containers: array<int, array<string, mixed>>,
     *   networks: array<int, array<string, mixed>>,
     *   serviceMappings: array<int, array<string, mixed>>,
     *   warnings: string[]
     * }
     */
    public function analyze(string $catalogBaseDir, array $entries): array
    {
        return $this->analyzeEntries($catalogBaseDir, $entries, []);
    }

    /**
     * Analyze one project root without requiring a flow-services catalog.
     *
     * @return array{
     *   detectedComposeFiles: string[],
     *   dockerfiles: string[],
     *   environmentFiles: string[],
     *   containers: array<int, array<string, mixed>>,
     *   networks: array<int, array<string, mixed>>,
     *   serviceMappings: array<int, array<string, mixed>>,
     *   warnings: string[]
     * }
     */
    public function analyzeProject(string $projectRoot): array
    {
        $root = realpath($projectRoot) ?: $projectRoot;
        $entries = [[
            'path' => $root,
            'name' => basename(rtrim($root, DIRECTORY_SEPARATOR)),
            'hostnames' => [],
            'contractEndpoints' => null,
            'docker' => [
                'composeFiles' => [],
                'dockerfiles' => [],
                'envFiles' => [],
                'serviceNames' => [],
            ],
        ]];

        $result = $this->analyzeEntries($root, $entries, $this->discoverComposeFilesRecursive($root));
        $result['dockerfiles'] = array_values(array_unique(array_merge(
            $result['dockerfiles'],
            $this->discoverDockerfilesRecursive($root)
        )));

        return $result;
    }

    /**
     * @param array<int, array{
     *   path: string,
     *   name: string|null,
     *   hostnames: string[],
     *   contractEndpoints: array<int, array{method: string, path: string, summary: string}>|null,
     *   docker: array{
     *     composeFiles: string[],
     *     dockerfiles: string[],
     *     envFiles: string[],
     *     serviceNames: string[]
     *   }
     * }> $entries
     * @param string[] $extraComposeFiles
     * @return array{
     *   detectedComposeFiles: string[],
     *   dockerfiles: string[],
     *   environmentFiles: string[],
     *   containers: array<int, array<string, mixed>>,
     *   networks: array<int, array<string, mixed>>,
     *   serviceMappings: array<int, array<string, mixed>>,
     *   warnings: string[]
     * }
     */
    private function analyzeEntries(string $catalogBaseDir, array $entries, array $extraComposeFiles): array
    {
        $composeFiles = $extraComposeFiles;
        foreach ($entries as $entry) {
            $root = realpath($entry['path']) ?: $entry['path'];
            $docker = $entry['docker'] ?? [];
            $composeFiles = array_merge(
                $composeFiles,
                $this->discoverComposeFiles($root),
                $this->discoverComposeFiles($catalogBaseDir),
                $this->existingPaths($docker['composeFiles'] ?? [])
            );
        }
        $composeFiles = array_values(array_unique($composeFiles));

        $composeServices = [];
        $dockerfiles = [];
        $environmentFiles = [];
        $warnings = [];
        $networks = [];

        foreach ($composeFiles as $composeFile) {
            $parsed = $this->yamlParser->parseFile($composeFile);
            $services = is_array($parsed['services'] ?? null) ? $parsed['services'] : [];
            $composeDir = dirname($composeFile);

            foreach ($services as $composeServiceName => $definition) {
                if (!is_array($definition)) {
                    continue;
                }

                $container = $this->buildComposeService((string) $composeServiceName, $definition, $composeFile, $composeDir);
                $composeServices[$container['composeFile'] . '|' . $container['name']] = $container;
                $dockerfiles = array_merge($dockerfiles, $container['dockerfiles']);
                $environmentFiles = array_merge($environmentFiles, $container['environmentFiles']);
            }

            $networks = array_merge($networks, $this->extractNetworks($composeFile, $services, $parsed['networks'] ?? []));
        }

        $serviceMappings = [];
        foreach ($entries as $entry) {
            $root = realpath($entry['path']) ?: $entry['path'];
            $serviceName = $entry['name'] ?? basename(rtrim($root, DIRECTORY_SEPARATOR));
            $docker = $entry['docker'] ?? [];
            $matched = $this->matchComposeServices($serviceName, $root, $docker, $composeServices);

            $hostnames = $entry['hostnames'] ?? [];
            $mappingDockerfiles = $this->existingPaths($docker['dockerfiles'] ?? []);
            $mappingEnvFiles = $this->existingPaths($docker['envFiles'] ?? []);
            $environments = [];
            $containerSummaries = [];

            foreach ($matched as $container) {
                $hostnames = array_merge($hostnames, $container['hostnames']);
                $mappingDockerfiles = array_merge($mappingDockerfiles, $container['dockerfiles']);
                $mappingEnvFiles = array_merge($mappingEnvFiles, $container['environmentFiles']);
                $environments[] = $container['environment'];
                $containerSummaries[] = [
                    'name' => $container['name'],
                    'image' => $container['image'],
                    'containerName' => $container['containerName'],
                    'ports' => $container['ports'],
                    'dependsOn' => $container['dependsOn'],
                    'networks' => $container['networks'],
                    'environmentKeys' => $container['environmentKeys'],
                    'restart' => $container['restart'],
                    'healthcheck' => $container['healthcheck'],
                    'logging' => $container['logging'],
                ];
            }

            if ($matched === []) {
                $warnings[] = "No Docker service mapping detected for catalog service \"{$serviceName}\".";
            }

            $serviceMappings[] = [
                'service' => $serviceName,
                'root' => $root,
                'composeServices' => array_values(array_map(
                    static fn(array $container): string => $container['name'],
                    $matched
                )),
                'hostnames' => array_values(array_unique(array_filter($hostnames, 'is_string'))),
                'dockerfiles' => array_values(array_unique($mappingDockerfiles)),
                'environmentFiles' => array_values(array_unique($mappingEnvFiles)),
                'environments' => array_values(array_unique(array_filter($environments))),
                'containers' => $containerSummaries,
            ];
        }

        return [
            'detectedComposeFiles' => $composeFiles,
            'dockerfiles' => array_values(array_unique($dockerfiles)),
            'environmentFiles' => array_values(array_unique($environmentFiles)),
            'containers' => array_values($composeServices),
            'networks' => $this->dedupeByNameAndSource($networks),
            'serviceMappings' => $serviceMappings,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /**
     * @return string[]
     */
    private function discoverComposeFiles(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $patterns = [
            'docker-compose*.yml',
            'docker-compose*.yaml',
            'compose*.yml',
            'compose*.yaml',
        ];

        $files = [];
        foreach ($patterns as $pattern) {
            $matches = glob($dir . DIRECTORY_SEPARATOR . $pattern) ?: [];
            foreach ($matches as $match) {
                if (is_file($match)) {
                    $files[] = realpath($match) ?: $match;
                }
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * @return string[]
     */
    private function discoverComposeFilesRecursive(string $dir): array
    {
        return $this->discoverFilesRecursive(
            $dir,
            static fn(string $baseName): bool => preg_match('/^(docker-compose.*|compose.*)\.ya?ml$/', $baseName) === 1
        );
    }

    /**
     * @return string[]
     */
    private function discoverDockerfilesRecursive(string $dir): array
    {
        return $this->discoverFilesRecursive(
            $dir,
            static fn(string $baseName): bool => $baseName === 'Dockerfile' || str_starts_with($baseName, 'Dockerfile.')
        );
    }

    /**
     * @param callable(string): bool $matches
     * @return string[]
     */
    private function discoverFilesRecursive(string $dir, callable $matches): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $ignoredDirectories = [
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

        $files = [];
        $queue = [realpath($dir) ?: $dir];
        $visitedDirectories = [];
        while ($queue !== []) {
            $currentDir = array_pop($queue);
            if (!is_string($currentDir) || !is_dir($currentDir)) {
                continue;
            }

            $currentRealPath = realpath($currentDir) ?: $currentDir;
            if (isset($visitedDirectories[$currentRealPath])) {
                continue;
            }
            $visitedDirectories[$currentRealPath] = true;

            foreach (scandir($currentDir) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                $path = $currentDir . DIRECTORY_SEPARATOR . $entry;
                if (is_link($path)) {
                    continue;
                }

                if (is_dir($path)) {
                    $realPath = realpath($path) ?: $path;
                    if (!in_array($entry, $ignoredDirectories, true) && !isset($visitedDirectories[$realPath])) {
                        $queue[] = $path;
                    }
                    continue;
                }

                if (is_file($path) && $matches($entry)) {
                    $files[] = realpath($path) ?: $path;
                }
            }
        }

        sort($files);
        return array_values(array_unique($files));
    }

    /**
     * @param string[] $paths
     * @return string[]
     */
    private function existingPaths(array $paths): array
    {
        $resolved = [];
        foreach ($paths as $path) {
            if (!is_string($path) || trim($path) === '') {
                continue;
            }

            $full = realpath($path) ?: $path;
            if (file_exists($full)) {
                $resolved[] = $full;
            }
        }

        return array_values(array_unique($resolved));
    }

    /**
     * @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    private function buildComposeService(string $name, array $definition, string $composeFile, string $composeDir): array
    {
        $build = is_array($definition['build'] ?? null) ? $definition['build'] : null;
        $buildContext = null;
        $dockerfile = null;

        if (is_string($definition['build'] ?? null)) {
            $buildContext = $this->resolveRelativePath($composeDir, (string) $definition['build']);
        } elseif ($build !== null) {
            $context = is_string($build['context'] ?? null) ? (string) $build['context'] : '.';
            $buildContext = $this->resolveRelativePath($composeDir, $context);

            if (is_string($build['dockerfile'] ?? null) && $build['dockerfile'] !== '') {
                $dockerfile = $this->resolveRelativePath($buildContext ?? $composeDir, (string) $build['dockerfile']);
            }
        }

        if ($dockerfile === null) {
            $defaultDockerfile = ($buildContext ?? $composeDir) . DIRECTORY_SEPARATOR . 'Dockerfile';
            if (is_file($defaultDockerfile)) {
                $dockerfile = realpath($defaultDockerfile) ?: $defaultDockerfile;
            }
        }

        $networkNames = $this->extractNetworkNames($definition['networks'] ?? []);
        $aliases = $this->extractAliases($definition['networks'] ?? []);
        $envFiles = $this->extractEnvFiles($definition['env_file'] ?? [], $composeDir);
        $profiles = $this->stringList($definition['profiles'] ?? []);
        $hostnames = array_merge(
            [$name],
            $aliases,
            $this->stringList([$definition['hostname'] ?? null, $definition['container_name'] ?? null])
        );

        $serviceEnvironment = $this->inferEnvironment(implode(' ', array_merge(
            [$name, (string) ($definition['container_name'] ?? ''), (string) ($definition['image'] ?? '')],
            $profiles,
            $envFiles
        )));
        $environment = $serviceEnvironment !== 'unknown'
            ? $serviceEnvironment
            : $this->inferEnvironment($composeFile);

        return [
            'name' => $name,
            'composeFile' => $composeFile,
            'image' => is_string($definition['image'] ?? null) ? (string) $definition['image'] : null,
            'buildContext' => $buildContext,
            'dockerfiles' => $dockerfile !== null ? [$dockerfile] : [],
            'containerName' => is_string($definition['container_name'] ?? null) ? (string) $definition['container_name'] : null,
            'ports' => $this->stringList($definition['ports'] ?? []),
            'volumes' => $this->stringList($definition['volumes'] ?? []),
            'dependsOn' => $this->extractDependsOn($definition['depends_on'] ?? []),
            'networks' => $networkNames,
            'aliases' => $aliases,
            'hostnames' => array_values(array_unique(array_filter($hostnames))),
            'environmentFiles' => $envFiles,
            'environmentKeys' => $this->extractEnvironmentKeys($definition['environment'] ?? []),
            'profiles' => $profiles,
            'environment' => $environment,
            'restart' => is_string($definition['restart'] ?? null) ? (string) $definition['restart'] : null,
            'healthcheck' => $this->summarizeHealthcheck($definition['healthcheck'] ?? null),
            'logging' => $this->summarizeLogging($definition['logging'] ?? null),
        ];
    }

    /**
     * @param mixed $value
     * @return string[]
     */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            if (is_string($value) && trim($value) !== '') {
                return [trim($value)];
            }
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_string($item) && trim($item) !== '') {
                $items[] = trim($item);
                continue;
            }
            if (is_scalar($item) && trim((string) $item) !== '') {
                $items[] = trim((string) $item);
            }
        }

        return array_values(array_unique($items));
    }

    /**
     * @param mixed $value
     * @return string[]
     */
    private function extractDependsOn(mixed $value): array
    {
        if (is_array($value)) {
            if (array_keys($value) === range(0, count($value) - 1)) {
                return $this->stringList($value);
            }

            return array_values(array_unique(array_map(
                static fn(string $name): string => trim($name),
                array_keys($value)
            )));
        }

        return $this->stringList($value);
    }

    /**
     * @param mixed $value
     * @return string[]
     */
    private function extractEnvFiles(mixed $value, string $composeDir): array
    {
        $files = [];
        foreach ($this->stringList($value) as $path) {
            $resolved = $this->resolveRelativePath($composeDir, $path);
            if (file_exists($resolved)) {
                $files[] = $resolved;
            } else {
                $files[] = $resolved;
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * @param mixed $value
     * @return string[]
     */
    private function extractNetworkNames(mixed $value): array
    {
        if (!is_array($value)) {
            return $this->stringList($value);
        }

        if (array_keys($value) === range(0, count($value) - 1)) {
            return $this->stringList($value);
        }

        return array_values(array_unique(array_map(
            static fn(string $name): string => trim($name),
            array_keys($value)
        )));
    }

    /**
     * @param mixed $value
     * @return string[]
     */
    private function extractAliases(mixed $value): array
    {
        if (!is_array($value) || array_keys($value) === range(0, count($value) - 1)) {
            return [];
        }

        $aliases = [];
        foreach ($value as $network) {
            if (!is_array($network)) {
                continue;
            }

            $aliases = array_merge($aliases, $this->stringList($network['aliases'] ?? []));
        }

        return array_values(array_unique($aliases));
    }

    /**
     * @return string[]
     */
    private function extractEnvironmentKeys(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $keys = [];
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $keys[] = trim($key);
                continue;
            }

            if (!is_string($item)) {
                continue;
            }

            $name = str_contains($item, '=')
                ? trim(explode('=', $item, 2)[0])
                : trim($item);

            if ($name !== '' && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) === 1) {
                $keys[] = $name;
            }
        }

        return array_values(array_unique(array_filter($keys)));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function summarizeHealthcheck(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $summary = [];
        foreach (['test', 'interval', 'timeout', 'retries', 'start_period'] as $key) {
            if (array_key_exists($key, $value)) {
                $summary[$key] = $value[$key];
            }
        }

        return $summary !== [] ? $summary : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function summarizeLogging(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $summary = [];
        if (is_string($value['driver'] ?? null)) {
            $summary['driver'] = $value['driver'];
        }
        if (is_array($value['options'] ?? null)) {
            $summary['options'] = $value['options'];
        }

        return $summary !== [] ? $summary : null;
    }

    private function resolveRelativePath(string $baseDir, string $path): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1) {
            return realpath($path) ?: $path;
        }

        $combined = $baseDir . DIRECTORY_SEPARATOR . $path;
        return realpath($combined) ?: $combined;
    }

    private function inferEnvironment(string $value): string
    {
        $lower = strtolower($value);
        if (preg_match('/\b(prod|production|live)\b/', $lower) === 1) {
            return 'production';
        }
        if (preg_match('/\b(stage|staging|preprod|hml)\b/', $lower) === 1) {
            return 'staging';
        }
        if (preg_match('/\b(dev|development|local)\b/', $lower) === 1) {
            return 'development';
        }
        if (preg_match('/\b(test|ci)\b/', $lower) === 1) {
            return 'test';
        }

        return 'unknown';
    }

    /**
     * @param array<string, array<string, mixed>> $composeServices
     * @return array<int, array<string, mixed>>
     */
    private function matchComposeServices(string $serviceName, string $root, array $docker, array $composeServices): array
    {
        $expectedNames = $docker['serviceNames'] ?? [];
        $expectedNames = is_array($expectedNames) ? $expectedNames : [];
        $expectedNames = array_values(array_unique(array_filter(array_map(
            static fn(mixed $name): string => is_string($name) ? trim($name) : '',
            $expectedNames
        ))));

        $serviceToken = $this->normalizeToken($serviceName);
        $rootToken = $this->normalizeToken(basename(rtrim($root, DIRECTORY_SEPARATOR)));

        $matched = [];
        foreach ($composeServices as $container) {
            $tokens = [
                $this->normalizeToken((string) ($container['name'] ?? '')),
                $this->normalizeToken((string) ($container['containerName'] ?? '')),
                $this->normalizeToken((string) ($container['image'] ?? '')),
            ];

            $explicitMatch = false;
            foreach ($expectedNames as $expectedName) {
                if ($this->normalizeToken($expectedName) === $tokens[0]) {
                    $explicitMatch = true;
                    break;
                }
            }

            if ($explicitMatch
                || in_array($serviceToken, $tokens, true)
                || in_array($rootToken, $tokens, true)
                || $this->pathMatchesBuildContext($root, (string) ($container['buildContext'] ?? ''))
            ) {
                $matched[] = $container;
            }
        }

        return $matched;
    }

    private function normalizeToken(string $value): string
    {
        $parts = preg_split('/[\/:@]/', strtolower($value)) ?: [];
        $last = end($parts);
        return preg_replace('/[^a-z0-9]+/', '', (string) $last) ?: '';
    }

    private function pathMatchesBuildContext(string $root, string $buildContext): bool
    {
        if ($buildContext === '') {
            return false;
        }

        $normalizedRoot = rtrim(str_replace('\\', '/', realpath($root) ?: $root), '/');
        $normalizedBuild = rtrim(str_replace('\\', '/', realpath($buildContext) ?: $buildContext), '/');

        return $normalizedRoot === $normalizedBuild || str_starts_with($normalizedBuild, $normalizedRoot . '/');
    }

    /**
     * @param array<string, mixed> $services
     * @param mixed $declaredNetworks
     * @return array<int, array<string, mixed>>
     */
    private function extractNetworks(string $composeFile, array $services, mixed $declaredNetworks): array
    {
        $networkMembers = [];
        foreach ($services as $serviceName => $definition) {
            if (!is_array($definition)) {
                continue;
            }

            foreach ($this->extractNetworkNames($definition['networks'] ?? []) as $networkName) {
                $networkMembers[$networkName][] = (string) $serviceName;
            }
        }

        $items = [];
        $declared = is_array($declaredNetworks) ? $declaredNetworks : [];
        $allNames = array_values(array_unique(array_merge(array_keys($declared), array_keys($networkMembers))));
        foreach ($allNames as $networkName) {
            $items[] = [
                'name' => (string) $networkName,
                'composeFile' => $composeFile,
                'services' => array_values(array_unique($networkMembers[$networkName] ?? [])),
                'external' => (bool) (($declared[$networkName]['external'] ?? false) === true),
            ];
        }

        return $items;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function dedupeByNameAndSource(array $items): array
    {
        $deduped = [];
        foreach ($items as $item) {
            $key = (string) ($item['name'] ?? '') . '|' . (string) ($item['composeFile'] ?? '');
            $deduped[$key] = $item;
        }

        return array_values($deduped);
    }
}
