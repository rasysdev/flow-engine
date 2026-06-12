<?php

namespace FlowEngine\Application\CLI\Command;

use FlowEngine\Application\AppMap\ApplicationMapBuilder;
use FlowEngine\Application\AppMap\OpenApiContractParser;
use FlowEngine\Application\AppMap\ServiceInfo;
use FlowEngine\Bootstrap\Container;
use FlowEngine\Bootstrap\InfraServices;
use FlowEngine\Console\ConsoleIO;

final class AppMapCommand implements Command
{
    public function __construct(
        private ConsoleIO $io
    ) {
    }

    public function supports(string $command): bool
    {
        return $command === 'appmap';
    }

    public function handle(array $argv): void
    {
        $catalog = $this->extractOption($argv, '--catalog');

        $entries = $catalog !== null
            ? $this->loadCatalogEntries($catalog)
            : array_map(
                fn($p) => [
                    'path' => $p,
                    'name' => null,
                    'hostnames' => [],
                    'contractEndpoints' => null,
                    'docker' => [
                        'composeFiles' => [],
                        'dockerfiles' => [],
                        'envFiles' => [],
                        'serviceNames' => [],
                    ],
                ],
                array_values(array_filter(array_slice($argv, 2), fn($p) => $p !== '' && !str_starts_with($p, '--')))
            );

        if (count($entries) < 2) {
            $this->io->error('Usage: flow appmap <project_path_a> <project_path_b> [project_path_c ...]');
            $this->io->info('Or:    flow appmap --catalog=flow-services.json');
            $this->io->info('Each project path must contain a flow-engine.json file.');
            return;
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
                hostnames: $entry['hostnames'] ?? [],
                contractEndpoints: $entry['contractEndpoints'] ?? null,
            );
        }

        $builder = new ApplicationMapBuilder();
        $map = $builder->build($services);

        $this->io->json([
            'status' => 'ok',
            'appmap' => $map,
        ]);
    }

    private function extractOption(array $argv, string $prefix): ?string
    {
        foreach ($argv as $arg) {
            if (str_starts_with($arg, $prefix . '=')) {
                return substr($arg, strlen($prefix) + 1);
            }
        }

        return null;
    }

    private function loadCatalogEntries(string $catalogPath): array
    {
        return (new InfraServices())->resolveCatalogServices()->enrichedEntries($catalogPath);
    }

    /**
     * @return string[]
     */
    private function scanFiles(Container $container): array
    {
        return $container->projectFiles();
    }
}
