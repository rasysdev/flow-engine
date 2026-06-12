<?php

namespace FlowEngine\Application\InfraMap\Contract;

interface DockerTopologyReader
{
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
    public function analyze(string $catalogBaseDir, array $entries): array;

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
    public function analyzeProject(string $projectRoot): array;
}
