<?php

namespace FlowEngine\Application\ProjectMap;

use FlowEngine\AI\Export\FlowDiagramGenerator;
use FlowEngine\Application\AppMap\MermaidDiagramGenerator;
use FlowEngine\Application\AppMap\ServiceInfo;
use FlowEngine\Application\DTO\ProjectBatchLookupDTO;
use FlowEngine\Application\DTO\ProjectLookupDTO;
use FlowEngine\Application\Snippet\SnippetExtractor;
use FlowEngine\Domain\Contracts\Flow as FlowContract;
use FlowEngine\Domain\Flow\Edge;
use FlowEngine\Domain\Flow\FlowTrace;
use FlowEngine\Domain\Flow\FlowTracer;
use FlowEngine\Domain\Flow\Node;
use FlowEngine\Domain\Flow\TraceDirection;

final class ProjectLookupBuilder
{
    public function __construct(
        private ?ProjectMapBuilder $mapBuilder = null
    ) {
        $this->mapBuilder ??= new ProjectMapBuilder();
    }

    public function lookupProject(
        string $projectRoot,
        FlowContract $flow,
        string $targetType,
        string $target,
        string $format,
        int $depth
    ): ProjectLookupDTO|string {
        $depth = max(1, $depth);

        return match ($targetType) {
            'area' => $format === 'diagram'
                ? $this->renderProjectAreaDiagram($projectRoot, $flow, $target)
                : $this->lookupProjectArea($flow, $target, $format, $depth),
            'entrypoint', 'node' => $format === 'diagram'
                ? $this->renderProjectNodeDiagram($projectRoot, $flow, $target, $depth)
                : $this->lookupProjectNode($flow, $targetType, $target, $format, $depth),
            'boundary' => $format === 'diagram'
                ? $this->renderProjectBoundaryDiagram($projectRoot, $flow, $target)
                : $this->lookupProjectBoundary($flow, $target, $format, $depth),
            'path' => $format === 'diagram'
                ? $this->renderProjectPathDiagram($projectRoot, $flow, $target, $depth)
                : $this->lookupProjectPath($flow, $target, $format, $depth),
            'service' => throw new \InvalidArgumentException('targetType=service is only valid when using catalog'),
            default => throw new \InvalidArgumentException('Invalid targetType. Use area, entrypoint, node, service, boundary, or path'),
        };
    }

    /**
     * @param array<string, mixed> $appmap
     * @param ServiceInfo[] $services
     */
    public function lookupCatalog(
        string $catalogPath,
        array $appmap,
        array $services,
        string $targetType,
        string $target,
        string $format,
        int $depth
    ): ProjectLookupDTO|string {
        $depth = max(1, $depth);

        return match ($targetType) {
            'service' => $format === 'diagram'
                ? $this->renderCatalogServiceDiagram($catalogPath, $appmap, $target)
                : $this->lookupCatalogService($services, $appmap, $target, $format, $depth),
            'boundary' => $format === 'diagram'
                ? $this->renderCatalogBoundaryDiagram($catalogPath, $services, $appmap, $target)
                : $this->lookupCatalogBoundary($services, $appmap, $target, $format, $depth),
            'path' => $format === 'diagram'
                ? $this->renderCatalogPathDiagram($catalogPath, $appmap, $target)
                : $this->lookupCatalogPath($appmap, $target, $format),
            'area' => $format === 'diagram'
                ? $this->renderCatalogAreaDiagram($catalogPath, $services, $appmap, $target)
                : $this->lookupCatalogArea($services, $appmap, $target, $format),
            'entrypoint', 'node' => $this->lookupCatalogNode($catalogPath, $services, $targetType, $target, $format, $depth),
            default => throw new \InvalidArgumentException('Invalid targetType. Use area, entrypoint, node, service, boundary, or path'),
        };
    }

    /**
     * Expand the literal "all" string to concrete target identifiers for a given targetType.
     * Rejects node and path (too many results / doesn't make sense as batch).
     *
     * @return string[]
     */
    public function expandAllTargets(FlowContract $flow, string $targetType): array
    {
        return match ($targetType) {
            'area' => array_values(array_unique(array_map(
                fn(Node $node): string => $this->mapBuilder->areaNameForNode($node),
                $flow->nodes()
            ))),
            'entrypoint' => array_values(array_map(
                static fn(Node $node): string => $node->id(),
                $this->mapBuilder->actionableEntrypoints($flow)
            )),
            'boundary' => array_values(array_unique(array_merge(
                array_map(
                    fn(Node $node): string => $this->mapBuilder->classifyEntrypoint($node),
                    $this->mapBuilder->actionableEntrypoints($flow)
                ),
                $this->externalHttpCalls($flow) !== [] ? ['external-http'] : []
            ))),
            'node' => throw new \InvalidArgumentException(
                'targets="all" is not supported for targetType=node (too many nodes). '
                . 'Use flow_find to search by name, or set targetType=entrypoint to list actionable entrypoints.'
            ),
            'path' => throw new \InvalidArgumentException(
                'targets="all" is not supported for targetType=path. '
                . 'Path lookup requires a single entrypoint identifier. Use targetType=entrypoint first.'
            ),
            default => throw new \InvalidArgumentException(
                'Invalid targetType. Use area, entrypoint, boundary, service, node, or path.'
            ),
        };
    }

    /**
     * Expand "all" for catalog scope (service targetType only makes sense here).
     *
     * @param ServiceInfo[] $services
     * @param array<string, mixed> $appmap
     * @return string[]
     */
    public function expandAllCatalogTargets(array $services, string $targetType, array $appmap = []): array
    {
        return match ($targetType) {
            'service' => array_map(static fn(ServiceInfo $s): string => $s->name, $services),
            'area' => array_values(array_unique(array_map(
                fn(ServiceInfo $s): string => $this->serviceAreaName($s->name),
                $services
            ))),
            'entrypoint' => array_values(array_unique(array_merge(...array_map(
                fn(ServiceInfo $s): array => array_map(
                    static fn(Node $node): string => $node->id(),
                    $this->mapBuilder->actionableEntrypoints($s->flow)
                ),
                $services
            )))),
            'boundary' => array_values(array_unique(array_merge(
                // Boundaries from service entrypoints (via spread of per-service arrays)
                array_merge(...array_map(
                    fn(ServiceInfo $s): array => array_map(
                        fn(Node $node): string => $this->mapBuilder->classifyEntrypoint($node),
                        $this->mapBuilder->actionableEntrypoints($s->flow)
                    ),
                    $services
                )),
                // Boundaries from cross-service integration edges
                array_values(array_filter(array_map(
                    static fn($edge): string => is_array($edge) ? (string) ($edge['type'] ?? '') : '',
                    (array) ($appmap['integrationEdges'] ?? [])
                ), static fn(string $t): bool => $t !== ''))
            ))),
            'node' => throw new \InvalidArgumentException(
                'targets="all" is not supported for targetType=node. '
                . 'Use flow_find or targetType=entrypoint.'
            ),
            'path' => throw new \InvalidArgumentException(
                'targets="all" is not supported for targetType=path. '
                . 'Path lookup requires a single entrypoint identifier.'
            ),
            default => throw new \InvalidArgumentException(
                'Invalid targetType. Use area, entrypoint, node, service, boundary, or path.'
            ),
        };
    }

    /**
     * Batch lookup for single-project scope.
     *
     * @param string[] $targets
     */
    public function lookupProjectBatch(
        string $projectRoot,
        FlowContract $flow,
        string $targetType,
        array $targets,
        string $format,
        int $depth,
        int $limit
    ): ProjectBatchLookupDTO {
        $depth = max(1, $depth);

        // Validate targetType once before the loop — invalid targetType is a caller error,
        // not a per-target not-found. This throws InvalidArgumentException if targetType is invalid
        // or not applicable for single-project scope (e.g. targetType=service).
        $this->assertValidProjectTargetType($targetType);

        // Dedup preserving order
        $targets = array_values(array_unique($targets));
        $totalRequested = count($targets);

        // Apply limit: truncate and track dropped
        $truncated = false;
        $droppedTargets = [];
        if (count($targets) > $limit) {
            $droppedTargets = array_slice($targets, $limit);
            $targets = array_slice($targets, 0, $limit);
            $truncated = true;
        }

        $results = [];
        $notFound = [];

        foreach ($targets as $target) {
            try {
                $result = $this->lookupProject($projectRoot, $flow, $targetType, $target, $format, $depth);
                if ($result instanceof ProjectLookupDTO) {
                    $results[] = $result->toArray();
                }
                // string result = diagram, should not happen in batch (validated upstream)
            } catch (\InvalidArgumentException $e) {
                // Not-found in batch does not throw — reported in notFound
                $notFound[] = $target;
            }
        }

        return new ProjectBatchLookupDTO(
            kind: 'lookup_batch_result',
            targetType: $targetType,
            format: $format,
            totalRequested: $totalRequested,
            returned: count($results),
            truncated: $truncated,
            results: $results,
            notFound: $notFound,
            droppedTargets: $droppedTargets,
            warnings: [],
        );
    }

    /**
     * Batch lookup for catalog scope.
     *
     * @param array<string, mixed> $appmap
     * @param ServiceInfo[] $services
     * @param string[] $targets
     */
    public function lookupCatalogBatch(
        string $catalogPath,
        array $appmap,
        array $services,
        string $targetType,
        array $targets,
        string $format,
        int $depth,
        int $limit
    ): ProjectBatchLookupDTO {
        $depth = max(1, $depth);

        // Validate targetType once before the loop — invalid targetType is a caller error,
        // not a per-target not-found.
        $this->assertValidCatalogTargetType($targetType);

        // Dedup preserving order
        $targets = array_values(array_unique($targets));
        $totalRequested = count($targets);

        // Apply limit
        $truncated = false;
        $droppedTargets = [];
        if (count($targets) > $limit) {
            $droppedTargets = array_slice($targets, $limit);
            $targets = array_slice($targets, 0, $limit);
            $truncated = true;
        }

        $results = [];
        $notFound = [];

        foreach ($targets as $target) {
            try {
                $result = $this->lookupCatalog($catalogPath, $appmap, $services, $targetType, $target, $format, $depth);
                if ($result instanceof ProjectLookupDTO) {
                    $results[] = $result->toArray();
                }
            } catch (\InvalidArgumentException $e) {
                $notFound[] = $target;
            }
        }

        return new ProjectBatchLookupDTO(
            kind: 'lookup_batch_result',
            targetType: $targetType,
            format: $format,
            totalRequested: $totalRequested,
            returned: count($results),
            truncated: $truncated,
            results: $results,
            notFound: $notFound,
            droppedTargets: $droppedTargets,
            warnings: [],
        );
    }

    private function lookupProjectArea(FlowContract $flow, string $target, string $format, int $depth): ProjectLookupDTO
    {
        $nodes = array_values(array_filter(
            $flow->nodes(),
            fn(Node $node): bool => $this->mapBuilder->areaNameForNode($node) === $target
        ));

        if ($nodes === []) {
            throw new \InvalidArgumentException("Unknown area target: {$target}");
        }

        $nodeIds = array_fill_keys(array_map(static fn(Node $node): string => $node->id(), $nodes), true);
        $dependencies = [];
        $dependents = [];

        foreach ($flow->edges() as $edge) {
            if (!$this->isRelevantLookupEdge($edge)) {
                continue;
            }

            $fromInArea = isset($nodeIds[$edge->from()]);
            $toInArea = isset($nodeIds[$edge->to()]);

            if ($fromInArea && !$toInArea) {
                $dependencies[$edge->to()] = ['nodeId' => $edge->to(), 'type' => $edge->type()];
            }
            if ($toInArea && !$fromInArea) {
                $dependents[$edge->from()] = ['nodeId' => $edge->from(), 'type' => $edge->type()];
            }
        }

        $entrypoints = array_values(array_filter(
            $this->mapBuilder->actionableEntrypoints($flow),
            fn(Node $node): bool => $this->mapBuilder->areaNameForNode($node) === $target
        ));

        $context = $format === 'context'
            ? [
                'nodeCount' => count($nodes),
                'files' => array_values(array_unique(array_map(static fn(Node $node): string => $node->file(), $nodes))),
                'entrypoints' => array_map(fn(Node $node): array => $this->nodeReference($node), $entrypoints),
            ]
            : null;

        return new ProjectLookupDTO(
            kind: 'lookup_result',
            targetType: 'area',
            target: $target,
            whyItMatters: sprintf('Area %s contains %d nodes and %d actionable entrypoint(s).', $target, count($nodes), count($entrypoints)),
            relatedAreas: $this->relatedAreasForNodes($flow, $nodes),
            directDependencies: array_values($dependencies),
            directDependents: array_values($dependents),
            relevantBoundaries: $this->boundariesForNodes($nodes),
            recommendedReads: $this->recommendedReadsFromNodes($entrypoints !== [] ? $entrypoints : $nodes, $depth),
            recommendedNextLookups: array_merge(
                [['targetType' => 'area', 'target' => $target, 'reason' => 'Use format=diagram to visualize cross-area edges.']],
                array_map(
                    static fn(Node $node): array => [
                        'targetType' => 'node',
                        'target' => $node->id(),
                        'reason' => 'Inspect one representative node inside this area.',
                    ],
                    array_slice($entrypoints !== [] ? $entrypoints : $nodes, 0, max(1, $depth))
                )
            ),
            warnings: [],
            context: $context,
        );
    }

    private function lookupProjectNode(FlowContract $flow, string $targetType, string $target, string $format, int $depth): ProjectLookupDTO
    {
        $node = $flow->node($target);
        if ($node === null) {
            throw new \InvalidArgumentException("Unknown {$targetType} target: {$target}");
        }

        $tracer = new FlowTracer($flow);
        $trace = $tracer->trace($target, TraceDirection::BOTH, max(1, $depth + 1));
        $dependencies = $this->neighborsForNode($flow, $target, 'downstream');
        $dependents = $this->neighborsForNode($flow, $target, 'upstream');

        $context = $format === 'context'
            ? [
                'traceDepth' => $trace->depth(),
                'pathPreviews' => array_map(
                    fn(array $path): array => array_map([$this, 'shortNodeId'], $path),
                    array_slice($trace->paths(), 0, max(1, $depth))
                ),
                'node' => $this->nodeReference($node),
            ]
            : null;

        $nodeMetadata = $node->metadata();
        $endLine = is_array($nodeMetadata) && isset($nodeMetadata['endLine']) && is_int($nodeMetadata['endLine'])
            ? $nodeMetadata['endLine']
            : null;
        $snippet = SnippetExtractor::extract($node->file(), $node->line(), $endLine);

        return new ProjectLookupDTO(
            kind: 'lookup_result',
            targetType: $targetType,
            target: $target,
            whyItMatters: $this->nodeImportanceText($node, $dependencies, $dependents),
            relatedAreas: $this->relatedAreasForTrace($flow, $trace),
            directDependencies: $dependencies,
            directDependents: $dependents,
            relevantBoundaries: $this->boundariesForNodes([$node]),
            recommendedReads: $this->recommendedReadsFromNodes($trace->nodes(), $depth),
            recommendedNextLookups: [
                [
                    'targetType' => 'area',
                    'target' => $this->mapBuilder->areaNameForNode($node),
                    'reason' => 'Inspect the broader area around this node.',
                ],
                [
                    'targetType' => 'path',
                    'target' => 'path:' . $node->id(),
                    'reason' => 'Expand the dominant path around this node.',
                ],
            ],
            warnings: [],
            context: $context,
            snippet: $snippet,
        );
    }

    private function lookupProjectBoundary(FlowContract $flow, string $target, string $format, int $depth): ProjectLookupDTO
    {
        $nodes = array_values(array_filter(
            $this->mapBuilder->actionableEntrypoints($flow),
            fn(Node $node): bool => $this->mapBuilder->classifyEntrypoint($node) === $target
        ));

        if ($nodes === [] && $target !== 'external-http') {
            throw new \InvalidArgumentException("Unknown boundary target: {$target}");
        }

        $context = null;
        $warnings = [];
        if ($target === 'external-http') {
            $externalCalls = $this->externalHttpCalls($flow);
            if ($externalCalls === []) {
                throw new \InvalidArgumentException('Unknown boundary target: external-http');
            }
            $context = $format === 'context' ? ['calls' => $externalCalls] : null;
            $recommendedReads = array_map(
                static fn(array $item): array => [
                    'targetType' => 'node',
                    'target' => $item['fromNode'],
                    'reason' => 'This node triggers an outbound HTTP call.',
                ],
                array_slice($externalCalls, 0, max(1, $depth))
            );
        } else {
            $recommendedReads = $this->recommendedReadsFromNodes($nodes, $depth);
            $context = $format === 'context'
                ? ['examples' => array_map(fn(Node $node): array => $this->nodeReference($node), $nodes)]
                : null;
        }

        return new ProjectLookupDTO(
            kind: 'lookup_result',
            targetType: 'boundary',
            target: $target,
            whyItMatters: $target === 'external-http'
                ? 'This boundary represents outbound HTTP calls to systems outside the analyzed graph.'
                : "Boundary {$target} groups real runtime entrypoints that bring traffic or execution into the project.",
            relatedAreas: $target === 'external-http' ? [] : $this->relatedAreasForNodes($flow, $nodes),
            directDependencies: $target === 'external-http' ? [] : [],
            directDependents: $target === 'external-http' ? [] : [],
            relevantBoundaries: [['name' => $target]],
            recommendedReads: $recommendedReads,
            recommendedNextLookups: $target === 'external-http'
                ? []
                : array_map(
                    static fn(Node $node): array => [
                        'targetType' => 'entrypoint',
                        'target' => $node->id(),
                        'reason' => 'Inspect one concrete entrypoint in this boundary.',
                    ],
                    array_slice($nodes, 0, max(1, $depth))
                ),
            warnings: $warnings,
            context: $context,
        );
    }

    private function lookupProjectPath(FlowContract $flow, string $target, string $format, int $depth): ProjectLookupDTO
    {
        $entrypoint = $this->entrypointFromPathTarget($target);
        if ($entrypoint === null || $flow->node($entrypoint) === null) {
            throw new \InvalidArgumentException("Unknown path target: {$target}");
        }

        $tracer = new FlowTracer($flow);
        $trace = $tracer->trace($entrypoint, TraceDirection::DOWNSTREAM, max(1, $depth + 1));
        $path = $this->longestPath($trace);

        return new ProjectLookupDTO(
            kind: 'lookup_result',
            targetType: 'path',
            target: $target,
            whyItMatters: 'This path expands one deterministic runtime path starting from the selected entrypoint.',
            relatedAreas: $this->relatedAreasForTrace($flow, $trace),
            directDependencies: array_map(static fn(string $nodeId): array => ['nodeId' => $nodeId], array_slice($path, 1)),
            directDependents: [],
            relevantBoundaries: [['name' => $this->mapBuilder->classifyEntrypoint($flow->node($entrypoint))]],
            recommendedReads: array_map(
                fn(string $nodeId): array => ['targetType' => 'node', 'target' => $nodeId, 'reason' => 'This node is part of the selected path.'],
                array_slice($path, 0, max(1, $depth + 1))
            ),
            recommendedNextLookups: [
                [
                    'targetType' => 'entrypoint',
                    'target' => $entrypoint,
                    'reason' => 'Inspect the full entrypoint around this path.',
                ],
            ],
            warnings: [],
            context: $format === 'context'
                ? ['pathNodes' => $path, 'pathPreview' => array_map([$this, 'shortNodeId'], $path)]
                : null,
        );
    }

    /**
     * @param ServiceInfo[] $services
     * @param array<string, mixed> $appmap
     */
    private function lookupCatalogService(array $services, array $appmap, string $target, string $format, int $depth): ProjectLookupDTO
    {
        $service = $this->findService($services, $target);
        if ($service === null) {
            throw new \InvalidArgumentException("Unknown service target: {$target}");
        }

        [$dependencies, $dependents] = $this->serviceDependencies($appmap, $target);
        $entrypoints = array_slice($this->mapBuilder->actionableEntrypoints($service->flow), 0, max(1, $depth));

        return new ProjectLookupDTO(
            kind: 'lookup_result',
            targetType: 'service',
            target: $target,
            whyItMatters: sprintf(
                'Service %s has %d nodes, %d direct dependency edge(s), and %d direct dependent edge(s).',
                $target,
                $service->flow->nodeCount(),
                count($dependencies),
                count($dependents)
            ),
            relatedAreas: [[
                'name' => $this->serviceAreaName($target),
                'languages' => $service->languages(),
            ]],
            directDependencies: $dependencies,
            directDependents: $dependents,
            relevantBoundaries: $this->boundariesForNodes($entrypoints),
            recommendedReads: array_map(
                fn(Node $node): array => [
                    'targetType' => 'entrypoint',
                    'target' => $node->id(),
                    'reason' => 'This entrypoint is a good first read inside the service.',
                ],
                $entrypoints
            ),
            recommendedNextLookups: [
                [
                    'targetType' => 'service',
                    'target' => $target,
                    'reason' => 'Use format=diagram to visualize the service and its neighbors.',
                ],
            ],
            warnings: [],
            context: $format === 'context'
                ? [
                    'root' => $service->root,
                    'languages' => $service->languages(),
                    'hostnames' => $service->hostnames,
                    'configResolution' => $service->configResolution,
                ]
                : null,
        );
    }

    /**
     * @param ServiceInfo[] $services
     * @param array<string, mixed> $appmap
     */
    private function lookupCatalogBoundary(array $services, array $appmap, string $target, string $format, int $depth): ProjectLookupDTO
    {
        $entrypoints = [];
        foreach ($services as $service) {
            foreach ($this->mapBuilder->actionableEntrypoints($service->flow) as $node) {
                if ($this->mapBuilder->classifyEntrypoint($node) === $target) {
                    $entrypoints[] = ['service' => $service->name, 'node' => $node];
                }
            }
        }

        $integrationExamples = array_values(array_filter(
            (array) ($appmap['integrationEdges'] ?? []),
            static fn($edge): bool => is_array($edge) && (string) ($edge['type'] ?? '') === $target
        ));

        if ($entrypoints === [] && $integrationExamples === []) {
            throw new \InvalidArgumentException("Unknown boundary target: {$target}");
        }

        return new ProjectLookupDTO(
            kind: 'lookup_result',
            targetType: 'boundary',
            target: $target,
            whyItMatters: "Boundary {$target} shows how work enters or crosses services in the catalog.",
            relatedAreas: array_map(
                static fn(string $area): array => ['name' => $area],
                array_values(array_unique(array_map(
                    fn(array $item): string => $this->serviceAreaName($item['service']),
                    $entrypoints
                )))
            ),
            directDependencies: [],
            directDependents: [],
            relevantBoundaries: [['name' => $target]],
            recommendedReads: array_map(
                static fn(array $item): array => [
                    'targetType' => 'entrypoint',
                    'target' => $item['node']->id(),
                    'reason' => 'Inspect one representative entrypoint on this boundary.',
                ],
                array_slice($entrypoints, 0, max(1, $depth))
            ),
            recommendedNextLookups: array_map(
                static fn(array $edge): array => [
                    'targetType' => 'path',
                    'target' => sprintf(
                        'path:%s->%s:%s',
                        (string) ($edge['fromService'] ?? ''),
                        (string) ($edge['toService'] ?? 'external'),
                        (string) ($edge['type'] ?? '')
                    ),
                    'reason' => 'Inspect one cross-service path on this boundary.',
                ],
                array_slice($integrationExamples, 0, max(1, $depth))
            ),
            warnings: [],
            context: $format === 'context'
                ? ['integrationExamples' => array_slice($integrationExamples, 0, max(1, $depth * 2))]
                : null,
        );
    }

    /**
     * @param array<string, mixed> $appmap
     */
    private function lookupCatalogPath(array $appmap, string $target, string $format): ProjectLookupDTO
    {
        foreach (($appmap['serviceEdges'] ?? []) as $edge) {
            if (!is_array($edge)) {
                continue;
            }

            $candidate = sprintf(
                'path:%s->%s:%s',
                (string) ($edge['from'] ?? ''),
                (string) ($edge['to'] ?? ''),
                (string) ($edge['type'] ?? '')
            );

            if ($candidate !== $target) {
                continue;
            }

            return new ProjectLookupDTO(
                kind: 'lookup_result',
                targetType: 'path',
                target: $target,
                whyItMatters: 'This path represents one deterministic cross-service dependency in the catalog.',
                relatedAreas: [],
                directDependencies: [[
                    'service' => $edge['to'],
                    'type' => $edge['type'],
                    'count' => $edge['count'],
                ]],
                directDependents: [[
                    'service' => $edge['from'],
                    'type' => $edge['type'],
                    'count' => $edge['count'],
                ]],
                relevantBoundaries: [['name' => $edge['type']]],
                recommendedReads: [
                    [
                        'targetType' => 'service',
                        'target' => $edge['from'],
                        'reason' => 'Inspect the source service of this path.',
                    ],
                    [
                        'targetType' => 'service',
                        'target' => $edge['to'],
                        'reason' => 'Inspect the target service of this path.',
                    ],
                ],
                recommendedNextLookups: [],
                warnings: [],
                context: $format === 'context' ? ['serviceEdge' => $edge] : null,
            );
        }

        throw new \InvalidArgumentException("Unknown path target: {$target}");
    }

    /**
     * @param ServiceInfo[] $services
     * @param array<string, mixed> $appmap
     */
    private function lookupCatalogArea(array $services, array $appmap, string $target, string $format): ProjectLookupDTO
    {
        $matchingServices = array_values(array_filter(
            $services,
            fn(ServiceInfo $service): bool => $this->serviceAreaName($service->name) === $target
        ));

        if ($matchingServices === []) {
            throw new \InvalidArgumentException("Unknown area target: {$target}");
        }

        $serviceNames = array_map(static fn(ServiceInfo $service): string => $service->name, $matchingServices);

        return new ProjectLookupDTO(
            kind: 'lookup_result',
            targetType: 'area',
            target: $target,
            whyItMatters: sprintf('Area %s groups %d service(s) in the catalog.', $target, count($matchingServices)),
            relatedAreas: [],
            directDependencies: array_values(array_filter(
                (array) ($appmap['serviceEdges'] ?? []),
                static fn($edge): bool => is_array($edge) && in_array((string) ($edge['from'] ?? ''), $serviceNames, true)
            )),
            directDependents: array_values(array_filter(
                (array) ($appmap['serviceEdges'] ?? []),
                static fn($edge): bool => is_array($edge) && in_array((string) ($edge['to'] ?? ''), $serviceNames, true)
            )),
            relevantBoundaries: [],
            recommendedReads: array_map(
                static fn(ServiceInfo $service): array => [
                    'targetType' => 'service',
                    'target' => $service->name,
                    'reason' => 'Inspect one service inside this catalog area.',
                ],
                $matchingServices
            ),
            recommendedNextLookups: [],
            warnings: [],
            context: $format === 'context'
                ? ['services' => $serviceNames]
                : null,
        );
    }

    /**
     * @param ServiceInfo[] $services
     */
    private function lookupCatalogNode(
        string $catalogPath,
        array $services,
        string $targetType,
        string $target,
        string $format,
        int $depth
    ): ProjectLookupDTO|string {
        foreach ($services as $service) {
            if ($service->flow->node($target) === null) {
                continue;
            }

            if ($format === 'diagram') {
                return $this->renderProjectNodeDiagram($service->root, $service->flow, $target, $depth);
            }

            $dto = $this->lookupProjectNode($service->flow, $targetType, $target, $format, $depth);
            $payload = $dto->toArray();
            $context = is_array($payload['context'] ?? null) ? $payload['context'] : [];
            $context['catalog'] = basename($catalogPath);
            $context['service'] = $service->name;
            $context['configResolution'] = $service->configResolution;

            return new ProjectLookupDTO(
                kind: $payload['kind'],
                targetType: $payload['targetType'],
                target: $payload['target'],
                whyItMatters: $payload['whyItMatters'],
                relatedAreas: $payload['relatedAreas'],
                directDependencies: $payload['directDependencies'],
                directDependents: $payload['directDependents'],
                relevantBoundaries: $payload['relevantBoundaries'],
                recommendedReads: $payload['recommendedReads'],
                recommendedNextLookups: $payload['recommendedNextLookups'],
                warnings: $payload['warnings'],
                context: $context !== [] ? $context : null,
            );
        }

        throw new \InvalidArgumentException("Unknown {$targetType} target: {$target}");
    }

    private function renderProjectAreaDiagram(string $projectRoot, FlowContract $flow, string $target): string
    {
        $nodes = array_filter(
            $flow->nodes(),
            fn(Node $node): bool => $this->mapBuilder->areaNameForNode($node) === $target
        );

        if ($nodes === []) {
            throw new \InvalidArgumentException("Unknown area target: {$target}");
        }

        $subgraphIds = $this->expandNodesWithNeighbors($flow, array_map(static fn(Node $node): string => $node->id(), $nodes));
        $mermaid = (new FlowDiagramGenerator())->componentDiagram($flow, null, $subgraphIds);

        return $this->mermaidMarkdown("Area Diagram: {$target}", $projectRoot, $mermaid);
    }

    private function renderProjectNodeDiagram(string $projectRoot, FlowContract $flow, string $target, int $depth): string
    {
        if ($flow->node($target) === null) {
            throw new \InvalidArgumentException("Unknown node target: {$target}");
        }

        $mermaid = (new FlowDiagramGenerator())->flowchart($flow, new FlowTracer($flow), $target, max(1, $depth + 1));

        return $this->mermaidMarkdown("Flow Diagram: {$target}", $projectRoot, $mermaid);
    }

    private function renderProjectBoundaryDiagram(string $projectRoot, FlowContract $flow, string $target): string
    {
        if ($target === 'external-http') {
            $mermaid = (new FlowDiagramGenerator())->c4Context($flow, basename(rtrim($projectRoot, DIRECTORY_SEPARATOR)));
            return $this->mermaidMarkdown("Boundary Diagram: {$target}", $projectRoot, $mermaid);
        }

        $entrypoints = array_values(array_filter(
            $this->mapBuilder->actionableEntrypoints($flow),
            fn(Node $node): bool => $this->mapBuilder->classifyEntrypoint($node) === $target
        ));

        if ($entrypoints === []) {
            throw new \InvalidArgumentException("Unknown boundary target: {$target}");
        }

        $subgraphIds = $this->expandNodesWithNeighbors(
            $flow,
            array_map(static fn(Node $node): string => $node->id(), $entrypoints)
        );
        $mermaid = (new FlowDiagramGenerator())->componentDiagram($flow, null, $subgraphIds);

        return $this->mermaidMarkdown("Boundary Diagram: {$target}", $projectRoot, $mermaid);
    }

    private function renderProjectPathDiagram(string $projectRoot, FlowContract $flow, string $target, int $depth): string
    {
        $entrypoint = $this->entrypointFromPathTarget($target);
        if ($entrypoint === null || $flow->node($entrypoint) === null) {
            throw new \InvalidArgumentException("Unknown path target: {$target}");
        }

        return $this->renderProjectNodeDiagram($projectRoot, $flow, $entrypoint, $depth);
    }

    /**
     * @param array<string, mixed> $appmap
     */
    private function renderCatalogServiceDiagram(string $catalogPath, array $appmap, string $target): string
    {
        $services = (array) ($appmap['services'] ?? []);
        $serviceNames = array_values(array_map(
            static fn(array $service): string => (string) ($service['name'] ?? ''),
            array_filter($services, 'is_array')
        ));

        if (!in_array($target, $serviceNames, true)) {
            throw new \InvalidArgumentException("Unknown service target: {$target}");
        }

        $filtered = $this->filterAppMapForService($appmap, $target);
        $mermaid = (new MermaidDiagramGenerator())->dependencyGraph($filtered);

        return $this->mermaidMarkdown("Service Diagram: {$target}", $catalogPath, $mermaid);
    }

    /**
     * @param array<string, mixed> $appmap
     */
    /**
     * @param ServiceInfo[] $services
     * @param array<string, mixed> $appmap
     */
    private function renderCatalogAreaDiagram(string $catalogPath, array $services, array $appmap, string $target): string
    {
        $serviceNames = array_values(array_map(
            static fn(ServiceInfo $service): string => $service->name,
            array_values(array_filter(
                $services,
                fn(ServiceInfo $service): bool => $this->serviceAreaName($service->name) === $target
            ))
        ));

        if ($serviceNames === []) {
            throw new \InvalidArgumentException("Unknown area target: {$target}");
        }

        $filtered = $this->filterAppMapByServiceNames($appmap, $this->expandServiceNamesWithNeighbors($appmap, $serviceNames));
        $mermaid = (new MermaidDiagramGenerator())->dependencyGraph($filtered);

        return $this->mermaidMarkdown("Catalog Area Diagram: {$target}", $catalogPath, $mermaid);
    }

    /**
     * @param ServiceInfo[] $services
     * @param array<string, mixed> $appmap
     */
    private function renderCatalogBoundaryDiagram(string $catalogPath, array $services, array $appmap, string $target): string
    {
        $servicesForBoundary = [];

        foreach ($services as $service) {
            foreach ($this->mapBuilder->actionableEntrypoints($service->flow) as $node) {
                if ($this->mapBuilder->classifyEntrypoint($node) === $target) {
                    $servicesForBoundary[$service->name] = true;
                    break;
                }
            }
        }

        foreach ((array) ($appmap['integrationEdges'] ?? []) as $edge) {
            if (!is_array($edge) || (string) ($edge['type'] ?? '') !== $target) {
                continue;
            }
            $fromService = (string) ($edge['fromService'] ?? '');
            $toService = (string) ($edge['toService'] ?? '');
            if ($fromService !== '') {
                $servicesForBoundary[$fromService] = true;
            }
            if ($toService !== '') {
                $servicesForBoundary[$toService] = true;
            }
        }

        if ($servicesForBoundary === []) {
            throw new \InvalidArgumentException("Unknown boundary target: {$target}");
        }

        $filtered = $this->filterAppMapByServiceNames($appmap, $this->expandServiceNamesWithNeighbors($appmap, array_keys($servicesForBoundary)));
        $mermaid = (new MermaidDiagramGenerator())->dependencyGraph($filtered);

        return $this->mermaidMarkdown('Catalog Boundary Diagram', $catalogPath, $mermaid);
    }

    /**
     * @param array<string, mixed> $appmap
     */
    private function renderCatalogPathDiagram(string $catalogPath, array $appmap, string $target): string
    {
        foreach ((array) ($appmap['serviceEdges'] ?? []) as $edge) {
            if (!is_array($edge)) {
                continue;
            }

            $candidate = sprintf(
                'path:%s->%s:%s',
                (string) ($edge['from'] ?? ''),
                (string) ($edge['to'] ?? ''),
                (string) ($edge['type'] ?? '')
            );
            if ($candidate !== $target) {
                continue;
            }

            $filtered = $this->filterAppMapForServicePair($appmap, (string) $edge['from'], (string) $edge['to']);
            $mermaid = (new MermaidDiagramGenerator())->sequenceDiagram($filtered);
            return $this->mermaidMarkdown("Catalog Path Diagram: {$target}", $catalogPath, $mermaid);
        }

        throw new \InvalidArgumentException("Unknown path target: {$target}");
    }

    /**
     * @param Node[] $nodes
     * @return array<int, array<string, mixed>>
     */
    private function relatedAreasForNodes(FlowContract $flow, array $nodes): array
    {
        $nodeIds = array_fill_keys(array_map(static fn(Node $node): string => $node->id(), $nodes), true);
        $related = [];

        foreach ($flow->edges() as $edge) {
            if (!$this->isRelevantLookupEdge($edge)) {
                continue;
            }

            $fromNode = $flow->node($edge->from());
            $toNode = $flow->node($edge->to());
            if ($fromNode === null || $toNode === null) {
                continue;
            }

            if (isset($nodeIds[$edge->from()])) {
                $area = $this->mapBuilder->areaNameForNode($toNode);
                $related[$area] = ($related[$area] ?? 0) + 1;
            }
            if (isset($nodeIds[$edge->to()])) {
                $area = $this->mapBuilder->areaNameForNode($fromNode);
                $related[$area] = ($related[$area] ?? 0) + 1;
            }
        }

        $result = [];
        foreach ($related as $area => $count) {
            $result[] = ['name' => $area, 'connections' => $count];
        }

        usort($result, static fn(array $a, array $b): int => [$b['connections'], $a['name']] <=> [$a['connections'], $b['name']]);

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function relatedAreasForTrace(FlowContract $flow, FlowTrace $trace): array
    {
        return $this->relatedAreasForNodes($flow, $trace->nodes());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function neighborsForNode(FlowContract $flow, string $target, string $direction): array
    {
        $neighbors = [];
        foreach ($flow->edges() as $edge) {
            if (!$this->isRelevantLookupEdge($edge)) {
                continue;
            }

            if ($direction === 'downstream' && $edge->from() === $target) {
                $neighbors[] = [
                    'nodeId' => $edge->to(),
                    'type' => $edge->type(),
                ];
            }
            if ($direction === 'upstream' && $edge->to() === $target) {
                $neighbors[] = [
                    'nodeId' => $edge->from(),
                    'type' => $edge->type(),
                ];
            }
        }

        usort($neighbors, static fn(array $a, array $b): int => strcmp($a['nodeId'], $b['nodeId']));

        return $neighbors;
    }

    private function isRelevantLookupEdge(Edge $edge): bool
    {
        return in_array(
            $edge->type(),
            ['method_call', 'static_call', 'instantiation', 'direct_instantiation', 'http_call', 'wp_hook', 'import_call'],
            true
        );
    }

    /**
     * @param Node[] $nodes
     * @return array<int, array<string, mixed>>
     */
    private function boundariesForNodes(array $nodes): array
    {
        $boundaries = [];
        foreach ($nodes as $node) {
            $type = $this->mapBuilder->classifyEntrypoint($node);
            if ($type === 'custom') {
                continue;
            }
            $boundaries[$type] = ['name' => $type];
        }

        return array_values($boundaries);
    }

    /**
     * @param Node[] $nodes
     * @return array<int, array<string, mixed>>
     */
    private function recommendedReadsFromNodes(array $nodes, int $depth): array
    {
        $reads = [];
        foreach (array_slice($nodes, 0, max(1, $depth)) as $node) {
            if (!$node instanceof Node) {
                continue;
            }

            $reads[] = [
                'targetType' => 'node',
                'target' => $node->id(),
                'reason' => 'Read this node only after the map points here.',
                'file' => $node->file(),
            ];
        }

        return $reads;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function externalHttpCalls(FlowContract $flow): array
    {
        $nodeIds = array_fill_keys(array_map(static fn(Node $node): string => $node->id(), $flow->nodes()), true);
        $calls = [];

        foreach ($flow->edges() as $edge) {
            if ($edge->type() !== 'http_call' || isset($nodeIds[$edge->to()])) {
                continue;
            }
            $calls[] = [
                'fromNode' => $edge->from(),
                'target' => $this->httpTargetFromVirtualNode($edge->to()) ?? $edge->to(),
            ];
        }

        return $calls;
    }

    /**
     * @param ServiceInfo[] $services
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    private function serviceDependencies(array $appmap, string $target): array
    {
        $dependencies = [];
        $dependents = [];

        foreach ((array) ($appmap['serviceEdges'] ?? []) as $edge) {
            if (!is_array($edge)) {
                continue;
            }
            if ((string) ($edge['from'] ?? '') === $target) {
                $dependencies[] = [
                    'service' => (string) ($edge['to'] ?? ''),
                    'type' => (string) ($edge['type'] ?? ''),
                    'count' => (int) ($edge['count'] ?? 0),
                ];
            }
            if ((string) ($edge['to'] ?? '') === $target) {
                $dependents[] = [
                    'service' => (string) ($edge['from'] ?? ''),
                    'type' => (string) ($edge['type'] ?? ''),
                    'count' => (int) ($edge['count'] ?? 0),
                ];
            }
        }

        return [$dependencies, $dependents];
    }

    /**
     * @param ServiceInfo[] $services
     */
    private function findService(array $services, string $name): ?ServiceInfo
    {
        foreach ($services as $service) {
            if ($service->name === $name) {
                return $service;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $appmap
     * @return array<string, mixed>
     */
    private function filterAppMapForService(array $appmap, string $serviceName): array
    {
        $neighborNames = [$serviceName => true];
        foreach ((array) ($appmap['serviceEdges'] ?? []) as $edge) {
            if (!is_array($edge)) {
                continue;
            }
            if ((string) ($edge['from'] ?? '') === $serviceName) {
                $neighborNames[(string) ($edge['to'] ?? '')] = true;
            }
            if ((string) ($edge['to'] ?? '') === $serviceName) {
                $neighborNames[(string) ($edge['from'] ?? '')] = true;
            }
        }

        $names = array_keys($neighborNames);

        $filtered = $this->filterAppMapByServiceNames($appmap, $names);
        $filtered['integrationEdges'] = array_values(array_filter(
            (array) ($appmap['integrationEdges'] ?? []),
            static fn($edge): bool =>
                is_array($edge)
                && (
                    (string) ($edge['fromService'] ?? '') === $serviceName
                    || (string) ($edge['toService'] ?? '') === $serviceName
                )
        ));

        return $filtered;
    }

    /**
     * @param array<string, mixed> $appmap
     * @return array<string, mixed>
     */
    private function filterAppMapForServicePair(array $appmap, string $fromService, string $toService): array
    {
        $names = [$fromService, $toService];

        $filtered = $this->filterAppMapByServiceNames($appmap, $names);
        $filtered['integrationEdges'] = array_values(array_filter(
            (array) ($appmap['integrationEdges'] ?? []),
            static fn($edge): bool =>
                is_array($edge)
                && (string) ($edge['fromService'] ?? '') === $fromService
                && (
                    (string) ($edge['toService'] ?? '') === $toService
                    || (string) ($edge['target'] ?? '') !== ''
                )
        ));

        return $filtered;
    }

    private function serviceAreaName(string $serviceName): string
    {
        $parts = preg_split('/[-_.]/', $serviceName) ?: [$serviceName];
        $parts = array_values(array_filter($parts, static fn(string $part): bool => $part !== ''));

        if ($parts === []) {
            return $serviceName;
        }

        return implode('-', array_slice($parts, 0, min(2, count($parts))));
    }

    private function mermaidMarkdown(string $title, string $root, string $mermaid): string
    {
        $markdown  = "# {$title}\n\n";
        $markdown .= 'Generated: ' . date('c') . "\n";
        $markdown .= 'Root: ' . $root . "\n\n";
        $markdown .= "```mermaid\n{$mermaid}\n```\n";

        return $markdown;
    }

    private function entrypointFromPathTarget(string $target): ?string
    {
        if (!str_starts_with($target, 'path:')) {
            return null;
        }

        return substr($target, 5);
    }

    private function longestPath(FlowTrace $trace): array
    {
        $paths = $trace->paths();
        usort(
            $paths,
            static fn(array $a, array $b): int =>
                [count($b), implode('|', $a)] <=> [count($a), implode('|', $b)]
        );

        return $paths[0] ?? [$trace->startNodeId()];
    }

    /**
     * @return array<string, mixed>
     */
    private function nodeReference(Node $node): array
    {
        return [
            'nodeId' => $node->id(),
            'class' => $node->class(),
            'method' => $node->method(),
            'file' => $node->file(),
            'line' => $node->line(),
            'language' => $node->language(),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $dependencies
     * @param array<int, array<string, mixed>> $dependents
     */
    private function nodeImportanceText(Node $node, array $dependencies, array $dependents): string
    {
        return sprintf(
            'Node %s sits in area %s with %d direct dependency(ies) and %d direct dependent(s).',
            $node->id(),
            $this->mapBuilder->areaNameForNode($node),
            count($dependencies),
            count($dependents)
        );
    }

    private function shortNodeId(string $nodeId): string
    {
        if (!str_contains($nodeId, '::')) {
            return $nodeId;
        }

        [$class, $method] = explode('::', $nodeId, 2);
        $parts = preg_split('/[\\\\.]/', $class) ?: [$class];
        $shortClass = end($parts);

        return $shortClass . '::' . $method;
    }

    /**
     * Throws if targetType is not valid for single-project scope.
     * Called once before the batch loop so invalid targetType propagates as a real error,
     * not silently as a per-target not-found entry.
     */
    private function assertValidProjectTargetType(string $targetType): void
    {
        match ($targetType) {
            'area', 'entrypoint', 'node', 'boundary', 'path' => null,
            'service' => throw new \InvalidArgumentException('targetType=service is only valid when using catalog'),
            default => throw new \InvalidArgumentException('Invalid targetType. Use area, entrypoint, node, service, boundary, or path'),
        };
    }

    /**
     * Throws if targetType is not valid for catalog scope.
     * Called once before the batch loop so invalid targetType propagates as a real error,
     * not silently as a per-target not-found entry.
     */
    private function assertValidCatalogTargetType(string $targetType): void
    {
        match ($targetType) {
            'service', 'boundary', 'path', 'area', 'entrypoint', 'node' => null,
            default => throw new \InvalidArgumentException('Invalid targetType. Use area, entrypoint, node, service, boundary, or path'),
        };
    }

    private function httpTargetFromVirtualNode(string $value): ?string
    {
        if (!str_starts_with($value, 'http:')) {
            return null;
        }

        $parts = explode(':', $value, 3);
        return $parts[2] ?? null;
    }

    /**
     * @param string[] $nodeIds
     * @return array<string, true>
     */
    private function expandNodesWithNeighbors(FlowContract $flow, array $nodeIds): array
    {
        $lookup = array_fill_keys($nodeIds, true);

        foreach ($flow->edges() as $edge) {
            if (!$this->isRelevantLookupEdge($edge)) {
                continue;
            }

            if (isset($lookup[$edge->from()])) {
                $lookup[$edge->to()] = true;
            }
            if (isset($lookup[$edge->to()])) {
                $lookup[$edge->from()] = true;
            }
        }

        return $lookup;
    }

    /**
     * @param array<string, mixed> $appmap
     * @param string[] $serviceNames
     * @return string[]
     */
    private function expandServiceNamesWithNeighbors(array $appmap, array $serviceNames): array
    {
        $lookup = array_fill_keys($serviceNames, true);

        foreach ((array) ($appmap['serviceEdges'] ?? []) as $edge) {
            if (!is_array($edge)) {
                continue;
            }

            $from = (string) ($edge['from'] ?? '');
            $to = (string) ($edge['to'] ?? '');

            if ($from !== '' && isset($lookup[$from]) && $to !== '') {
                $lookup[$to] = true;
            }
            if ($to !== '' && isset($lookup[$to]) && $from !== '') {
                $lookup[$from] = true;
            }
        }

        return array_keys($lookup);
    }

    /**
     * @param array<string, mixed> $appmap
     * @param string[] $serviceNames
     * @return array<string, mixed>
     */
    private function filterAppMapByServiceNames(array $appmap, array $serviceNames): array
    {
        return [
            'services' => array_values(array_filter(
                (array) ($appmap['services'] ?? []),
                static fn($service): bool => is_array($service) && in_array((string) ($service['name'] ?? ''), $serviceNames, true)
            )),
            'serviceEdges' => array_values(array_filter(
                (array) ($appmap['serviceEdges'] ?? []),
                static fn($edge): bool =>
                    is_array($edge)
                    && in_array((string) ($edge['from'] ?? ''), $serviceNames, true)
                    && in_array((string) ($edge['to'] ?? ''), $serviceNames, true)
            )),
            'integrationEdges' => array_values(array_filter(
                (array) ($appmap['integrationEdges'] ?? []),
                static fn($edge): bool =>
                    is_array($edge)
                    && in_array((string) ($edge['fromService'] ?? ''), $serviceNames, true)
                    && (
                        ($edge['toService'] ?? null) === null
                        || in_array((string) ($edge['toService'] ?? ''), $serviceNames, true)
                    )
            )),
            'inconsistencies' => [],
        ];
    }
}
