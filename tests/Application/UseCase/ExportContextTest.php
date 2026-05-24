<?php

use PHPUnit\Framework\TestCase;
use FlowEngine\Application\UseCase\ExportContext;
use FlowEngine\Application\UseCase\AnalyzeMetrics;
use FlowEngine\Application\UseCase\AnalyzeComplexity;
use FlowEngine\Application\UseCase\AnalyzeCycles;
use FlowEngine\Application\UseCase\AnalyzeArchitecture;
use FlowEngine\Application\UseCase\AnalyzeOrphans;
use FlowEngine\AI\Export\ContextExporter;
use FlowEngine\AI\Export\MarkdownFormatter;
use FlowEngine\AI\Export\ExportOptions;
use Tests\Support\InMemoryFlowRepository;
use FlowEngine\Domain\Flow\Node;

final class ExportContextTest extends TestCase
{
    public function test_export_all_sections(): void
    {
        $useCase = $this->buildUseCase();
        $result = $useCase->execute(ExportOptions::all());

        $this->assertStringContainsString('## Metrics', $result->markdown);
        $this->assertStringContainsString('## Complexity', $result->markdown);
        $this->assertStringContainsString('## Dependency Cycles', $result->markdown);
        $this->assertStringContainsString('## Architecture', $result->markdown);
        $this->assertStringContainsString('## Orphan Code', $result->markdown);
        $this->assertCount(5, $result->includedSections);
        $this->assertGreaterThan(0, $result->tokenEstimate);
    }

    public function test_export_minimal(): void
    {
        $useCase = $this->buildUseCase();
        $result = $useCase->execute(ExportOptions::minimal());

        $this->assertStringContainsString('## Metrics', $result->markdown);
        $this->assertStringNotContainsString('## Complexity', $result->markdown);
        $this->assertCount(1, $result->includedSections);
    }

    public function test_token_estimate_is_reasonable(): void
    {
        $useCase = $this->buildUseCase();
        $result = $useCase->execute(ExportOptions::all());

        $expectedEstimate = (int) ceil(strlen($result->markdown) / 4);
        $this->assertSame($expectedEstimate, $result->tokenEstimate);
    }

    public function test_to_array_and_json(): void
    {
        $useCase = $this->buildUseCase();
        $result = $useCase->execute(ExportOptions::minimal());

        $array = $result->toArray();
        $this->assertArrayHasKey('markdown', $array);
        $this->assertArrayHasKey('tokenEstimate', $array);
        $this->assertArrayHasKey('includedSections', $array);

        $json = $result->toJson();
        $this->assertJson($json);
    }

    private function buildUseCase(): ExportContext
    {
        $repo = new InMemoryFlowRepository([
            new Node('App\\Service', 'handle', __FILE__, 1),
            new Node('App\\Repo', 'find', __FILE__, 2),
        ]);

        return new ExportContext(
            new AnalyzeMetrics($repo),
            new AnalyzeComplexity($repo),
            new AnalyzeCycles($repo),
            new AnalyzeArchitecture($repo),
            new AnalyzeOrphans($repo),
            new ContextExporter(new MarkdownFormatter())
        );
    }
}
