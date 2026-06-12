<?php

namespace FlowEngine\Application\InfraMap\Contract;

interface CatalogLoader
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
    public function load(string $catalogPath, ?string $projectPath = null): ?array;
}
