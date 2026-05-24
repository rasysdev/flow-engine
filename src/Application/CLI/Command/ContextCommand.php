<?php

namespace FlowEngine\Application\CLI\Command;

use FlowEngine\AI\Export\ExportOptions;
use FlowEngine\Application\AppMap\ApplicationMapBuilder;
use FlowEngine\Application\AppMap\ServiceInfo;
use FlowEngine\Bootstrap\Container;
use FlowEngine\Console\ConsoleIO;
use FlowEngine\Infrastructure\Config\FlowServiceCatalogLoader;
use FlowEngine\Infrastructure\Docker\DockerTopologyAnalyzer;

final class ContextCommand implements Command
{
    public function __construct(
        private ConsoleIO $io
    ) {
    }

    public function supports(string $command): bool
    {
        return $command === 'context';
    }

    public function handle(array $argv): void
    {
        $projectPath = $argv[2] ?? null;

        if (!$projectPath) {
            $this->io->error('Usage: flow context <project_path> [--minimal] [--sections=<s1,s2>] [--focus=<namespace>] [--entrypoint=<Class::method>] [--depth=N] [--catalog=<path>]');
            return;
        }

        $minimal = in_array('--minimal', $argv, true);
        $focusNamespace = null;
        $catalogPath = null;
        $entrypoint = null;
        $depth = 5;
        $strictEntrypoint = false;
        $sections = null;

        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--focus=')) {
                $focusNamespace = substr($arg, 8);
            }
            if (str_starts_with($arg, '--catalog=')) {
                $catalogPath = substr($arg, 10);
            }
            if (str_starts_with($arg, '--entrypoint=')) {
                $entrypoint = substr($arg, 13);
            }
            if (str_starts_with($arg, '--depth=')) {
                $depth = (int) substr($arg, 8);
            }
            if ($arg === '--strict-entrypoint') {
                $strictEntrypoint = true;
            }
            if (str_starts_with($arg, '--sections=')) {
                $sections = array_map('trim', explode(',', substr($arg, 11)));
            }
        }

        $includeServiceMap = $catalogPath !== null;

        if ($sections !== null) {
            $has = static fn(string $s): bool => in_array($s, $sections, true);
            $options = new ExportOptions(
                includeMetrics: $has('metrics'),
                includeComplexity: $has('complexity'),
                includeCycles: $has('cycles'),
                includeArchitecture: $has('architecture'),
                includeOrphans: $has('orphans'),
                includeServiceMap: $has('serviceMap') || $includeServiceMap,
                includeDataModel: $has('dataModel'),
                includeRoutes: $has('routes'),
                focusNamespace: $focusNamespace,
                catalogPath: $catalogPath,
                entrypoint: $entrypoint,
                entrypointDepth: $depth,
                strictEntrypoint: $strictEntrypoint,
            );
        } elseif ($minimal) {
            $options = new ExportOptions(
                includeMetrics: true,
                includeComplexity: false,
                includeCycles: false,
                includeArchitecture: false,
                includeOrphans: false,
                includeServiceMap: $includeServiceMap,
                includeDataModel: false,
                includeRoutes: false,
                focusNamespace: $focusNamespace,
                catalogPath: $catalogPath,
                entrypoint: $entrypoint,
                entrypointDepth: $depth,
                strictEntrypoint: $strictEntrypoint,
            );
        } else {
            $options = new ExportOptions(
                includeServiceMap: $includeServiceMap,
                includeDataModel: true,
                includeRoutes: true,
                focusNamespace: $focusNamespace,
                catalogPath: $catalogPath,
                entrypoint: $entrypoint,
                entrypointDepth: $depth,
                strictEntrypoint: $strictEntrypoint,
            );
        }

        $container = new Container($projectPath);
        $container->analyzeProject()->execute();

        $appmapData = null;
        if ($includeServiceMap && $catalogPath !== null) {
            $appmapData = $this->buildAppmap($catalogPath);
        }

        $exportContext = $container->exportContext($appmapData);
        $result = $exportContext->execute($options);

        echo $result->markdown;
        fwrite(STDERR, "[INFO] Token estimate: {$result->tokenEstimate}\n");
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildAppmap(string $catalogPath): ?array
    {
        $entries = $this->entriesFromCatalog($catalogPath);
        if (count($entries) < 2) {
            return null;
        }

        $services = [];
        foreach ($entries as $entry) {
            $root = realpath($entry['path']) ?: $entry['path'];
            $name = $entry['name'] ?? basename(rtrim($root, DIRECTORY_SEPARATOR));

            $container = new Container($root);
            $container->analyzeProject()->execute();

            $files = $this->scanFiles($container);

            $services[] = new ServiceInfo(
                name: $name,
                root: $root,
                flow: $container->getFlow(),
                files: $files,
                hostnames: $entry['hostnames'] ?? []
            );
        }

        return (new ApplicationMapBuilder())->build($services);
    }

    /**
     * @return array<int, array{path: string, name: string|null, hostnames: string[]}>
     */
    private function entriesFromCatalog(string $catalogPath): array
    {
        $catalog = (new FlowServiceCatalogLoader())->load($catalogPath);
        if ($catalog === null) {
            return [];
        }

        $entries = $catalog['entries'];
        $docker = (new DockerTopologyAnalyzer())->analyze($catalog['baseDir'], $entries);
        $hostnamesByService = [];
        foreach ($docker['serviceMappings'] as $mapping) {
            if (!is_array($mapping)) {
                continue;
            }
            $service = (string) ($mapping['service'] ?? '');
            if ($service === '') {
                continue;
            }
            $hostnamesByService[$service] = is_array($mapping['hostnames'] ?? null)
                ? array_values(array_filter($mapping['hostnames'], 'is_string'))
                : [];
        }

        return array_map(function (array $entry) use ($hostnamesByService): array {
            $serviceName = $entry['name'] ?? basename(rtrim($entry['path'], DIRECTORY_SEPARATOR));
            return [
                'path' => $entry['path'],
                'name' => $entry['name'],
                'hostnames' => array_values(array_unique(array_merge(
                    $entry['hostnames'] ?? [],
                    $hostnamesByService[$serviceName] ?? []
                ))),
            ];
        }, $entries);
    }

    /**
     * @return string[]
     */
    private function scanFiles(Container $container): array
    {
        return $container->projectFiles();
    }
}
