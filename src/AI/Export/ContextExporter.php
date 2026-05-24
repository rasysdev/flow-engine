<?php

namespace FlowEngine\AI\Export;

final class ContextExporter
{
    public function __construct(
        private MarkdownFormatter $formatter
    ) {
    }

    /**
     * @param array<string, mixed> $reports Keyed by section name: 'metrics', 'complexity', 'cycles', 'architecture', 'orphans', 'serviceMap', 'signatures', 'dataModel', 'routes'
     * @param ExportOptions $options
     * @return string Consolidated Markdown document
     */
    public function export(array $reports, ExportOptions $options): string
    {
        $sections = [];

        $sections[] = "# Flow Engine Analysis Report\n";

        if ($options->focusNamespace !== null) {
            $sections[] = "Focus: `{$options->focusNamespace}`\n";
        }

        if ($options->entrypoint !== null) {
            $sections[] = "**Entrypoint scope:** `{$options->entrypoint}` (depth: {$options->entrypointDepth})\n";
        }

        if ($options->includeServiceMap && isset($reports['serviceMap'])) {
            $sections[] = $this->formatter->formatServiceMap($reports['serviceMap']);
        }

        if ($options->includeMetrics && isset($reports['metrics'])) {
            $signatures = $reports['signatures'] ?? null;
            $sections[] = $this->formatter->formatMetrics($reports['metrics'], $signatures);
        }

        if ($options->includeComplexity && isset($reports['complexity'])) {
            $sections[] = $this->formatter->formatComplexity($reports['complexity']);
        }

        if ($options->includeCycles && isset($reports['cycles'])) {
            $sections[] = $this->formatter->formatCycles($reports['cycles']);
        }

        if ($options->includeArchitecture && isset($reports['architecture'])) {
            $sections[] = $this->formatter->formatArchitecture($reports['architecture']);
        }

        if ($options->includeOrphans && isset($reports['orphans'])) {
            $sections[] = $this->formatter->formatOrphans($reports['orphans']);
        }

        if ($options->includeDataModel && isset($reports['dataModel'])) {
            $sections[] = $this->formatter->formatDataModel($reports['dataModel']);
        }

        if ($options->includeRoutes && isset($reports['routes'])) {
            $sections[] = $this->formatter->formatRoutes($reports['routes']);
        }

        return implode("\n", $sections);
    }
}
