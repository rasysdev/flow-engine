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

        return $this->enrichEntries($entries, $docker);
    }

    /**
     * Load the catalog and run the Docker analysis once, returning both the
     * enriched entries and the raw Docker topology. Callers that need both
     * (e.g. the deployment map) must use this instead of calling
     * enrichedEntries() and a separate Docker analysis back to back, which
     * would parse the same catalog and recompute the topology twice.
     *
     * @return array{entries: array<int, array<string, mixed>>, docker: array<string, mixed>}
     */
    public function enrichedEntriesWithDocker(string $catalogPath, ?string $projectPath = null): array
    {
        $catalog = $this->catalogLoader->load($catalogPath, $projectPath);
        if ($catalog === null) {
            return ['entries' => [], 'docker' => $this->emptyDockerTopology()];
        }

        $entries = $catalog['entries'];
        $docker = $this->dockerTopologyReader->analyze($catalog['baseDir'], $entries);

        return ['entries' => $this->enrichEntries($entries, $docker), 'docker' => $docker];
    }

    /**
     * Merge the hostnames discovered in the Docker topology into each entry,
     * preserving the entry shape.
     *
     * @param array<int, array<string, mixed>> $entries
     * @param array<string, mixed> $docker
     * @return array<int, array<string, mixed>>
     */
    private function enrichEntries(array $entries, array $docker): array
    {
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
     * @return array<string, mixed>
     */
    private function emptyDockerTopology(): array
    {
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
