<?php

namespace FlowEngine\AI\Context;

use FlowEngine\Application\DTO\ArchitectureReportDTO;
use FlowEngine\Application\DTO\ComplexityReportDTO;
use FlowEngine\Application\DTO\CycleReportDTO;
use FlowEngine\Application\DTO\NodeImpactReportDTO;
use FlowEngine\Application\DTO\SafetyAssessmentDTO;
use FlowEngine\Application\DTO\ChangeRiskDTO;
use FlowEngine\Application\Visibility\VisibilityExplanation;
use FlowEngine\Domain\Flow\Flow;

/**
 * Ponte Domain → AI (ÚNICA PERMITIDA)
 * 
 * Traduz entidades de Domain para DTOs consumíveis pela camada AI.
 * Esta é a ÚNICA classe de AI que pode importar tipos de Domain.
 * 
 * REGRAS ARQUITETURAIS:
 * - ContextAssembler pode IMPORTAR entidades de Domain
 * - Nenhum outro código de AI pode depender diretamente de Domain
 * - Se AI precisar de dados de Domain, deve passar via DTO montado aqui
 * - DTOs retornados são readonly e serializáveis (JSON)
 * 
 * RESPONSABILIDADES:
 * - Converter Flow → FlowContext
 * - Extrair dados de Node → NodeContext
 * - Traduzir VisibilityExplanation → VisibilityContext
 * - Estruturar análise de impacto → ImpactContext
 * 
 * @internal Esta classe faz parte da fronteira arquitetural Domain ↔ AI
 */
final class ContextAssembler
{
    /**
     * Converte um Flow (grafo de execução) em DTO para IA.
     * 
     * @param Flow $flow Grafo de execução do domínio
     * @return FlowContext Representação serializável do grafo
     */
    public function flow(Flow $flow): FlowContext
    {
        $nodes = array_map(fn($n) => $n->id(), $flow->nodes());
        $edges = array_map(
            fn($e) => ['from' => $e->from(), 'to' => $e->to()],
            $flow->edges()
        );

        return new FlowContext($nodes, $edges);
    }

    /**
     * Extrai dados essenciais de um Node para DTO.
     * 
     * @param string $id Identificador único do nó (Class::method)
     * @param string $class Nome da classe
     * @param string $method Nome do método
     * @param string $visibility Visibilidade atual (public, internal, etc)
     * @return NodeContext Representação minimalista do nó
     */
    public function node(
        string $id,
        string $class,
        string $method,
        string $visibility
    ): NodeContext {
        return new NodeContext($id, $class, $method, $visibility);
    }

    /**
     * Converte explicação de visibilidade em DTO estruturado.
     * 
     * @param VisibilityExplanation $explanation Resultado da avaliação de policies
     * @return VisibilityContext Decisões de visibilidade serializáveis
     */
    public function visibility(VisibilityExplanation $explanation): VisibilityContext
    {
        return new VisibilityContext(
            $explanation->finalDecision->name,
            array_map(fn($i) => [
                'order' => $i->order,
                'policy' => $i->policyClass,
                'source' => $i->source->value,
                'decision' => $i->decision->name,
            ], $explanation->items)
        );
    }

    /**
     * Estrutura análise de impacto em formato consumível por IA.
     * 
     * @param array $impact Array com chaves 'upstream' e 'downstream'
     * @return ImpactContext Impacto estruturado
     */
    public function impact(array $impact): ImpactContext
    {
        return new ImpactContext(
            $impact['impact']['upstream'] ?? [],
            $impact['impact']['downstream'] ?? []
        );
    }

    /**
     * Converts a CycleReportDTO into a CycleContext for AI interpretation.
     */
    public function cycles(CycleReportDTO $report): CycleContext
    {
        return new CycleContext(
            totalCycles: $report->totalCycles,
            totalNodesInCycles: $report->totalNodesInCycles,
            bySeverity: $report->bySeverity,
            largestCycle: $report->largestCycle,
            cycles: $report->cycles
        );
    }

    /**
     * Converts a ComplexityReportDTO into a HotspotContext for AI interpretation.
     */
    public function hotspots(ComplexityReportDTO $report): HotspotContext
    {
        return new HotspotContext(
            totalMethods: $report->totalMethods,
            avgComplexity: $report->avgComplexity,
            maxComplexity: $report->maxComplexity,
            byLevel: $report->byLevel,
            complexMethods: $report->complexMethods
        );
    }

    /**
     * Converts an impact analysis result into a TraceContext for AI interpretation.
     *
     * @param string $nodeId The node being analyzed
     * @param array{node: string, impact: array{upstream: string[], downstream: string[]}} $impactResult
     */
    public function trace(string $nodeId, array $impactResult): TraceContext
    {
        return new TraceContext(
            nodeId: $nodeId,
            upstream: $impactResult['impact']['upstream'] ?? [],
            downstream: $impactResult['impact']['downstream'] ?? []
        );
    }

    /**
     * Converts an ArchitectureReportDTO into a ViolationContext for AI interpretation.
     */
    public function violations(ArchitectureReportDTO $report): ViolationContext
    {
        return new ViolationContext(
            isClean: $report->isClean,
            totalViolations: $report->totalViolations,
            bySeverity: $report->bySeverity,
            byType: $report->byType,
            layerDistribution: $report->layerDistribution,
            violations: $report->violations
        );
    }

    /**
     * Converts a NodeImpactReportDTO into a ChangeImpactContext for AI interpretation.
     */
    public function changeImpact(NodeImpactReportDTO $report): ChangeImpactContext
    {
        return new ChangeImpactContext(
            nodeId: $report->nodeId,
            upstream: $report->upstream,
            downstream: $report->downstream,
            blastRadius: $report->blastRadius,
            fanIn: $report->fanIn,
            fanOut: $report->fanOut,
            riskLevel: $report->riskLevel,
            complexityScore: $report->complexityScore,
            cyclesInvolved: $report->cyclesInvolved,
            violationsInvolved: $report->violationsInvolved,
            riskSummary: $report->riskSummary
        );
    }

    /**
     * Aggregates impact, safety, and risk data into RefactorPlanContext for AI plan generation.
     */
    public function refactorPlan(
        NodeImpactReportDTO $impact,
        SafetyAssessmentDTO $safety,
        ChangeRiskDTO $risk
    ): RefactorPlanContext {
        return new RefactorPlanContext(
            nodeId: $impact->nodeId,
            upstream: $impact->upstream,
            downstream: $impact->downstream,
            blastRadius: $impact->blastRadius,
            fanIn: $impact->fanIn,
            fanOut: $impact->fanOut,
            complexityScore: $impact->complexityScore,
            riskLevel: $risk->level,
            riskScore: $risk->score,
            riskFactors: $risk->factors,
            cyclesInvolved: $impact->cyclesInvolved,
            violationsInvolved: $impact->violationsInvolved,
            cyclesAffected: $safety->cyclesAffected,
            violationsAffected: $safety->violationsAffected,
            potentialOrphans: $safety->potentialOrphans,
            affectedNodeCount: $safety->affectedNodes
        );
    }
}