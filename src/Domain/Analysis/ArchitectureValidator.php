<?php

namespace FlowEngine\Domain\Analysis;

use FlowEngine\Domain\Contracts\Flow;

/**
 * Valida regras arquiteturais (Clean Architecture / DDD).
 * 
 * Dependency Rule (Clean Architecture):
 * - Domain não deve depender de nada (núcleo isolado)
 * - Application pode depender apenas de Domain
 * - Infrastructure pode depender de Application e Domain
 * 
 * Violações comuns:
 * - Domain → Infrastructure (acoplamento com detalhes)
 * - Domain → Application (inversão de dependência)
 * - Application → Infrastructure (acoplamento com detalhes)
 * 
 * Layers:
 * - Domain: Regras de negócio puras
 * - Application: Use cases e orquestração
 * - Infrastructure: Detalhes técnicos (DB, HTTP, etc)
 */
final class ArchitectureValidator
{
    /** @var array<string, string> Mapa de node -> layer */
    private array $nodeLayerMap = [];

    /** @var array<string, string[]> Custom layers: layer name => namespace prefixes */
    private array $customLayers;

    /**
     * @param array<string, string[]> $customLayers Layer name => namespace prefixes (priority over defaults)
     */
    public function __construct(
        private Flow $flow,
        array $customLayers = []
    ) {
        $this->customLayers = $customLayers;
        $this->buildLayerMap();
    }

    /**
     * Detecta todas as violações arquiteturais.
     * 
     * @api
     * @return array<int, array{from: string, to: string, fromLayer: string, toLayer: string, severity: string, reason: string}>
     */
    public function detectViolations(): array
    {
        $violations = [];
        $seen = [];

        foreach ($this->flow->edges() as $edge) {
            $fromLayer = $this->getNodeLayer($edge->from());
            $toLayer = $this->getNodeLayer($edge->to());

            // Verificar se é violação
            $violation = $this->checkViolation($fromLayer, $toLayer);

            if ($violation) {
                $key = implode('|', [
                    $edge->from(),
                    $edge->to(),
                    $fromLayer,
                    $toLayer,
                ]);

                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                $violations[] = [
                    'from' => $edge->from(),
                    'to' => $edge->to(),
                    'fromLayer' => $fromLayer,
                    'toLayer' => $toLayer,
                    'severity' => $violation['severity'],
                    'reason' => $violation['reason'],
                ];
            }
        }

        return $violations;
    }

    /**
     * Verifica se uma dependência é uma violação.
     * 
     * @return array{severity: string, reason: string}|null
     */
    private function checkViolation(string $fromLayer, string $toLayer): ?array
    {
        // Domain não pode depender de nada
        if ($fromLayer === 'Domain' && $toLayer === 'Infrastructure') {
            return [
                'severity' => 'CRITICAL',
                'reason' => 'Domain layer depends on Infrastructure (couples business logic to technical details)',
            ];
        }

        if ($fromLayer === 'Domain' && $toLayer === 'Application') {
            return [
                'severity' => 'CRITICAL',
                'reason' => 'Domain layer depends on Application (inverts dependency direction)',
            ];
        }

        // Application só pode depender de Domain
        if ($fromLayer === 'Application' && $toLayer === 'Infrastructure') {
            return [
                'severity' => 'HIGH',
                'reason' => 'Application layer depends on Infrastructure (couples use cases to technical details)',
            ];
        }

        // Sem violação
        return null;
    }

    /**
     * Constrói mapa de node → layer.
     */
    private function buildLayerMap(): void
    {
        foreach ($this->flow->nodes() as $node) {
            $metadataLayer = $node->metadata()['layer'] ?? null;
            if (is_string($metadataLayer) && $metadataLayer !== '') {
                $this->nodeLayerMap[$node->id()] = $metadataLayer;
                continue;
            }

            $this->nodeLayerMap[$node->id()] = $this->inferLayer($node->id());
        }
    }

    /**
     * Infere a camada de um node baseado no namespace.
     */
    private function inferLayer(string $nodeId): string
    {
        // Extrair namespace
        $parts = explode('::', $nodeId);
        $namespace = $parts[0] ?? '';

        // Priority 1: custom layers do config
        foreach ($this->customLayers as $layer => $prefixes) {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($namespace, $prefix)) {
                    return $layer;
                }
            }
        }

        // Priority 2: defaults (Clean Architecture namespaces)
        if (str_contains($namespace, '\\Domain\\')) {
            return 'Domain';
        }

        if (str_contains($namespace, '\\Application\\')) {
            return 'Application';
        }

        if (str_contains($namespace, '\\Infrastructure\\')) {
            return 'Infrastructure';
        }

        // Camadas alternativas (comum em outros projetos)
        if (str_contains($namespace, '\\Core\\') || str_contains($namespace, '\\Entity\\')) {
            return 'Domain';
        }

        if (str_contains($namespace, '\\Service\\') || str_contains($namespace, '\\UseCase\\')) {
            return 'Application';
        }

        if (str_contains($namespace, '\\Repository\\') || str_contains($namespace, '\\Controller\\')) {
            return 'Infrastructure';
        }

        return 'Unknown';
    }

    /**
     * Retorna a layer de um node.
     */
    private function getNodeLayer(string $nodeId): string
    {
        return $this->nodeLayerMap[$nodeId] ?? 'Unknown';
    }

    /**
     * Estatísticas de violações.
     * 
     * @api
     */
    public function stats(): array
    {
        $violations = $this->detectViolations();

        $bySeverity = [
            'CRITICAL' => 0,
            'HIGH' => 0,
        ];

        $byType = [
            'Domain → Infrastructure' => 0,
            'Domain → Application' => 0,
            'Application → Infrastructure' => 0,
        ];

        foreach ($violations as $violation) {
            $bySeverity[$violation['severity']]++;

            $type = "{$violation['fromLayer']} → {$violation['toLayer']}";
            if (isset($byType[$type])) {
                $byType[$type]++;
            }
        }

        return [
            'totalViolations' => count($violations),
            'bySeverity' => $bySeverity,
            'byType' => $byType,
        ];
    }

    /**
     * Verifica se a arquitetura está limpa.
     */
    public function isClean(): bool
    {
        return count($this->detectViolations()) === 0;
    }

    /**
     * Retorna distribuição de nodes por layer.
     * 
     * @api
     */
    public function layerDistribution(): array
    {
        $distribution = [
            'Domain' => 0,
            'Application' => 0,
            'Infrastructure' => 0,
            'Unknown' => 0,
        ];

        // Include custom layer names
        foreach (array_keys($this->customLayers) as $layer) {
            $distribution[$layer] ??= 0;
        }

        foreach ($this->nodeLayerMap as $layer) {
            $distribution[$layer] = ($distribution[$layer] ?? 0) + 1;
        }

        return $distribution;
    }
}
