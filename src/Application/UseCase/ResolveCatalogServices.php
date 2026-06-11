<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\Application\InfraMap\Contract\CatalogLoader;
use FlowEngine\Application\InfraMap\Contract\DockerTopologyReader;

final class ResolveCatalogServices
{
    public function __construct(
        private CatalogLoader $catalogLoader,
        private DockerTopologyReader $dockerTopologyReader,
    ) {
    }

    /**
     * Load a service catalog and return its entries enriched with the hostnames
     * discovered in the Docker topology. Returns [] when the catalog is invalid.
     *
     * @return array<int, array<string, mixed>>
     */
    public function enrichedEntries(string $catalogPath, ?string $projectPath = null): array
    {
        $catalog = $this->catalogLoader->load($catalogPath, $projectPath);
        if ($catalog === null) {
            return [];
        }

        $entries = $catalog['entries'];
        $docker = $this->dockerTopologyReader->analyze($catalog['baseDir'], $entries);
        $hostnamesByService = $this->hostnamesByService($docker);

        return array_map(function (array $entry) use ($hostnamesByService): array {
            $serviceName = $entry['name'] ?? basename(rtrim($entry['path'], DIRECTORY_SEPARATOR));
            $entry['hostnames'] = array_values(array_unique(array_merge(
                $entry['hostnames'] ?? [],
                $hostnamesByService[$serviceName] ?? []
            )));

            return $entry;
        }, $entries);
    }

    /**
     * Load a catalog and return the raw Docker topology for the given entries.
     * Returns an empty topology shape when the catalog is invalid.
     *
     * @param array<int, array<string, mixed>> $entries
     * @return array<string, mixed>
     */
    public function dockerTopology(string $catalogPath, array $entries, ?string $projectPath = null): array
    {
        $catalog = $this->catalogLoader->load($catalogPath, $projectPath);
        if ($catalog === null) {
            return [
                'detectedComposeFiles' => [],
                'dockerfiles' => [],
                'environmentFiles' => [],
                'containers' => [],
                'networks' => [],
                'serviceMappings' => [],
                'warnings' => [],
            ];
        }

        return $this->dockerTopologyReader->analyze($catalog['baseDir'], $entries);
    }

    /**
     * @param array<string, mixed> $docker
     * @return array<string, string[]>
     */
    private function hostnamesByService(array $docker): array
    {
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

        return $hostnamesByService;
    }
}
