<?php

use PHPUnit\Framework\TestCase;
use FlowEngine\AI\Export\ContextExporter;
use FlowEngine\AI\Export\MarkdownFormatter;
use FlowEngine\AI\Export\ExportOptions;
use FlowEngine\Application\DTO\MetricsReportDTO;
use FlowEngine\Application\DTO\ComplexityReportDTO;

final class ContextExporterTest extends TestCase
{
    private ContextExporter $exporter;

    protected function setUp(): void
    {
        $this->exporter = new ContextExporter(new MarkdownFormatter());
    }

    public function test_export_all_sections(): void
    {
        $reports = [
            'metrics' => $this->createMetrics(),
            'complexity' => $this->createComplexity(),
        ];

        $md = $this->exporter->export($reports, ExportOptions::all());

        $this->assertStringContainsString('# Flow Engine Analysis Report', $md);
        $this->assertStringContainsString('## Metrics', $md);
        $this->assertStringContainsString('## Complexity', $md);
    }

    public function test_export_minimal_excludes_optional_sections(): void
    {
        $reports = [
            'metrics' => $this->createMetrics(),
            'complexity' => $this->createComplexity(),
        ];

        $md = $this->exporter->export($reports, ExportOptions::minimal());

        $this->assertStringContainsString('## Metrics', $md);
        $this->assertStringNotContainsString('## Complexity', $md);
    }

    public function test_export_with_focus_namespace(): void
    {
        $options = new ExportOptions(focusNamespace: 'App\\Domain');
        $reports = ['metrics' => $this->createMetrics()];

        $md = $this->exporter->export($reports, $options);

        $this->assertStringContainsString('App\\Domain', $md);
    }

    public function test_export_skips_missing_reports(): void
    {
        $md = $this->exporter->export([], ExportOptions::all());

        $this->assertStringContainsString('# Flow Engine Analysis Report', $md);
        $this->assertStringNotContainsString('## Metrics', $md);
    }

    private function createMetrics(): MetricsReportDTO
    {
        return new MetricsReportDTO(
            totalNodes: 10,
            totalEdges: 5,
            avgFanIn: 1.0,
            avgFanOut: 1.0,
            maxFanIn: 2,
            maxFanOut: 2,
            hotspotCount: 0,
            hotspots: [],
            topCoupled: []
        );
    }

    private function createComplexity(): ComplexityReportDTO
    {
        return new ComplexityReportDTO(
            totalMethods: 10,
            avgComplexity: 2.0,
            maxComplexity: 5,
            minComplexity: 1,
            byLevel: [],
            complexMethods: []
        );
    }
}
