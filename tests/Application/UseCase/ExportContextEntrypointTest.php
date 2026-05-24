<?php

namespace Tests\Application\UseCase;

use FlowEngine\AI\Export\ContextExporter;
use FlowEngine\AI\Export\ExportOptions;
use FlowEngine\AI\Export\MarkdownFormatter;
use FlowEngine\Application\UseCase\AnalyzeArchitecture;
use FlowEngine\Application\UseCase\AnalyzeComplexity;
use FlowEngine\Application\UseCase\AnalyzeCycles;
use FlowEngine\Application\UseCase\AnalyzeMetrics;
use FlowEngine\Application\UseCase\AnalyzeOrphans;
use FlowEngine\Application\UseCase\ExportContext;
use FlowEngine\Domain\Flow\Edge;
use FlowEngine\Domain\Flow\Node;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryFlowRepository;

final class ExportContextEntrypointTest extends TestCase
{
    /**
     * Graph: Controller::store → Service::save → Repository::persist
     */
    private function buildUseCase(): ExportContext
    {
        $nodes = [
            new Node('App\\Controller', 'store', __FILE__, 1),
            new Node('App\\Service', 'save', __FILE__, 2),
            new Node('App\\Repository', 'persist', __FILE__, 3),
        ];

        $edges = [
            new Edge('App\\Controller::store', 'App\\Service::save', 'save'),
            new Edge('App\\Service::save', 'App\\Repository::persist', 'persist'),
        ];

        $repo = new InMemoryFlowRepository($nodes, $edges);

        return new ExportContext(
            new AnalyzeMetrics($repo),
            new AnalyzeComplexity($repo),
            new AnalyzeCycles($repo),
            new AnalyzeArchitecture($repo),
            new AnalyzeOrphans($repo),
            new ContextExporter(new MarkdownFormatter()),
            null,
            null,
            $repo->getFlow()
        );
    }

    public function test_entrypoint_scoping_includes_upstream_and_downstream(): void
    {
        $useCase = $this->buildUseCase();
        $options = new ExportOptions(
            includeMetrics: true,
            entrypoint: 'App\\Service::save',
            entrypointDepth: 5,
        );

        $result = $useCase->execute($options);

        // Header should mention entrypoint scope
        $this->assertStringContainsString('App\\Service::save', $result->markdown);
        $this->assertStringContainsString('Entrypoint scope', $result->markdown);
    }

    public function test_entrypoint_scoping_excludes_unrelated_nodes(): void
    {
        // Add an unrelated node that is not in the subgraph
        $nodes = [
            new Node('App\\Controller', 'store', __FILE__, 1),
            new Node('App\\Service', 'save', __FILE__, 2),
            new Node('App\\Repository', 'persist', __FILE__, 3),
            new Node('App\\Unrelated', 'doSomething', __FILE__, 4,  'php', ['params' => [['name' => '$x', 'type' => 'string']], 'returnType' => 'void']),
        ];

        $edges = [
            new Edge('App\\Controller::store', 'App\\Service::save', 'save'),
            new Edge('App\\Service::save', 'App\\Repository::persist', 'persist'),
        ];

        $repo = new InMemoryFlowRepository($nodes, $edges);
        $useCase = new ExportContext(
            new AnalyzeMetrics($repo),
            new AnalyzeComplexity($repo),
            new AnalyzeCycles($repo),
            new AnalyzeArchitecture($repo),
            new AnalyzeOrphans($repo),
            new ContextExporter(new MarkdownFormatter()),
            null,
            null,
            $repo->getFlow()
        );

        $options = new ExportOptions(
            includeMetrics: true,
            entrypoint: 'App\\Service::save',
            entrypointDepth: 5,
        );

        $result = $useCase->execute($options);

        // Unrelated node should not appear in signatures section
        $this->assertStringNotContainsString('Unrelated::doSomething', $result->markdown);
    }

    public function test_unknown_entrypoint_falls_back_to_full_export(): void
    {
        $useCase = $this->buildUseCase();
        $options = new ExportOptions(
            includeMetrics: true,
            entrypoint: 'Unknown::foo',
            entrypointDepth: 5,
        );

        // Must not throw; should export normally
        $result = $useCase->execute($options);

        $this->assertStringContainsString('## Metrics', $result->markdown);
        $this->assertGreaterThan(0, $result->tokenEstimate);
    }

    public function test_strict_entrypoint_returns_empty_when_node_not_found(): void
    {
        $useCase = $this->buildUseCase();
        $options = new ExportOptions(
            entrypoint: 'Unknown::foo',
            strictEntrypoint: true,
        );

        $result = $useCase->execute($options);

        $this->assertSame('', $result->markdown);
        $this->assertSame(0, $result->tokenEstimate);
        $this->assertEmpty($result->includedSections);
    }

    public function test_sections_with_entrypoint_includes_only_requested_sections(): void
    {
        $useCase = $this->buildUseCase();
        $options = new ExportOptions(
            includeMetrics: true,
            includeComplexity: false,
            includeCycles: false,
            includeArchitecture: false,
            includeOrphans: false,
            entrypoint: 'App\\Service::save',
        );

        $result = $useCase->execute($options);

        $this->assertStringContainsString('## Metrics', $result->markdown);
        $this->assertStringNotContainsString('## Complexity', $result->markdown);
        $this->assertStringNotContainsString('## Cycles', $result->markdown);
        $this->assertSame(['metrics'], $result->includedSections);
    }

    public function test_sections_metrics_and_cycles_with_entrypoint(): void
    {
        $useCase = $this->buildUseCase();
        $options = new ExportOptions(
            includeMetrics: true,
            includeComplexity: false,
            includeCycles: true,
            includeArchitecture: false,
            includeOrphans: false,
            entrypoint: 'App\\Service::save',
        );

        $result = $useCase->execute($options);

        $this->assertStringContainsString('## Metrics', $result->markdown);
        $this->assertStringContainsString('## Dependency Cycles', $result->markdown);
        $this->assertStringNotContainsString('## Complexity', $result->markdown);
        $this->assertSame(['metrics', 'cycles'], $result->includedSections);
    }

    public function test_no_entrypoint_exports_full_unchanged(): void
    {
        $useCase = $this->buildUseCase();

        $withoutEntrypoint = $useCase->execute(ExportOptions::all());
        $withEntrypoint = $useCase->execute(new ExportOptions(entrypoint: 'App\\Service::save'));

        // Without entrypoint: no scope note in header
        $this->assertStringNotContainsString('Entrypoint scope', $withoutEntrypoint->markdown);
        // With entrypoint: scope note present
        $this->assertStringContainsString('Entrypoint scope', $withEntrypoint->markdown);
    }
}
