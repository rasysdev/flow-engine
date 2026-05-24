<?php

namespace FlowEngine\AI\Export;

use FlowEngine\Domain\Flow\Edge;
use FlowEngine\Domain\Flow\Flow;
use FlowEngine\Domain\Flow\FlowTracer;
use FlowEngine\Domain\Flow\Node;
use FlowEngine\Domain\Flow\TraceDirection;

/**
 * Generates Mermaid diagrams from a single-project Flow graph.
 *
 * Three views:
 *   flowchart  — scoped call-graph from an entrypoint (flowchart LR)
 *   class      — UML-style class/method + inheritance (classDiagram)
 *   component  — namespace-level architecture (graph LR with subgraphs)
 *
 * @api
 */
final class FlowDiagramGenerator
{
    /**
     * Generates a flowchart scoped to the subgraph reachable from $entrypoint.
     *
     * @param Flow       $flow
     * @param FlowTracer $tracer     Pre-built tracer for $flow
     * @param string     $entrypoint Starting node ID (Class::method)
     * @param int        $depth      BFS depth (default 5)
     * @return string Mermaid flowchart LR source
     * @throws \InvalidArgumentException When entrypoint node does not exist
     */
    public function flowchart(Flow $flow, FlowTracer $tracer, string $entrypoint, int $depth = 5): string
    {
        $subgraphIds = $tracer->extractSubgraph($entrypoint, $depth, TraceDirection::BOTH);

        $nodes = array_filter($flow->nodes(), fn(Node $n) => isset($subgraphIds[$n->id()]));
        $edges = array_filter(
            $flow->edges(),
            fn(Edge $e) => isset($subgraphIds[$e->from()])
                && isset($subgraphIds[$e->to()])
                && !in_array($e->type(), ['property_access', 'trait_usage', 'interface_implementation'], true)
        );

        $lines = ['flowchart LR'];
        $lines[] = '  classDef entry fill:#4a90d9,stroke:#2c5f8a,color:#fff;';

        foreach ($nodes as $node) {
            $id = $this->nodeId($node->id());
            $label = $this->escape($this->shortName($node->class()) . '::' . $node->method() . '()');
            $lines[] = "  {$id}[\"{$label}\"]";
        }

        $lines[] = '  class ' . $this->nodeId($entrypoint) . ' entry';

        foreach ($edges as $edge) {
            $from = $this->nodeId($edge->from());
            $to   = $this->nodeId($edge->to());
            $label = $this->edgeArrowLabel($edge->type());
            $lines[] = $label !== ''
                ? "  {$from} -->|\"{$label}\"| {$to}"
                : "  {$from} --> {$to}";
        }

        return implode("\n", $lines);
    }

    /**
     * Generates a UML-style classDiagram.
     *
     * @param Flow                    $flow
     * @param string|null             $namespace   Optional namespace prefix filter
     * @param array<string,true>|null $subgraphIds Optional subgraph filter (from FlowTracer::extractSubgraph)
     * @return string Mermaid classDiagram source
     */
    public function classDiagram(Flow $flow, ?string $namespace = null, ?array $subgraphIds = null): string
    {
        $nodes = $flow->nodes();

        if ($subgraphIds !== null) {
            $nodes = array_filter($nodes, fn(Node $n) => isset($subgraphIds[$n->id()]));
        }

        if ($namespace !== null) {
            $nodes = array_filter($nodes, fn(Node $n) => str_starts_with($n->class(), $namespace));
        }

        /** @var array<string, string[]> $classes */
        $classes = [];
        foreach ($nodes as $node) {
            $classes[$node->class()][] = $node->method();
        }

        $lines = ['classDiagram'];

        foreach ($classes as $className => $methods) {
            $classId = $this->classId($className);
            $lines[] = "  class {$classId} {";
            foreach (array_unique($methods) as $method) {
                $lines[] = "    +{$method}()";
            }
            $lines[] = '  }';
            $short = $this->shortName($className);
            if ($short !== $className) {
                $lines[] = "  {$classId} : {$short}";
            }
        }

        // Trait / interface edges between classes in scope
        $classSet = array_fill_keys(array_keys($classes), true);
        foreach ($flow->edges() as $edge) {
            if (!in_array($edge->type(), ['trait_usage', 'interface_implementation'], true)) {
                continue;
            }
            [$fromClass] = explode('::', $edge->from(), 2);
            if (!isset($classSet[$fromClass])) {
                continue;
            }
            // edge.to looks like "App\LoggerTrait::__trait" — extract class part
            [$toClassRaw] = explode('::', $edge->to(), 2);
            $fromId = $this->classId($fromClass);
            $toId   = $this->classId($toClassRaw);
            $lines[] = "  {$fromId} ..|> {$toId}";
        }

        return implode("\n", $lines);
    }

    /**
     * Generates a component (namespace-level) diagram.
     *
     * Each unique top-2-segment namespace prefix becomes a subgraph.
     * Only cross-component edges are shown (deduped at component level).
     *
     * @param Flow                    $flow
     * @param string|null             $namespace   Optional parent namespace filter
     * @param array<string,true>|null $subgraphIds Optional subgraph filter
     * @return string Mermaid graph LR source
     */
    public function componentDiagram(Flow $flow, ?string $namespace = null, ?array $subgraphIds = null): string
    {
        $nodes = $flow->nodes();

        if ($subgraphIds !== null) {
            $nodes = array_filter($nodes, fn(Node $n) => isset($subgraphIds[$n->id()]));
        }

        if ($namespace !== null) {
            $nodes = array_filter($nodes, fn(Node $n) => str_starts_with($n->class(), $namespace));
        }

        /** @var array<string, Node[]> $components */
        $components = [];
        foreach ($nodes as $node) {
            $comp = $this->componentName($node->class());
            $components[$comp][] = $node;
        }

        $lines = ['graph LR'];

        foreach ($components as $comp => $compNodes) {
            $sgId  = $this->componentId($comp);
            $label = $this->escape($comp);
            $lines[] = "  subgraph {$sgId}[\"{$label}\"]";
            foreach ($compNodes as $node) {
                $nid       = $this->nodeId($node->id());
                $nodeLabel = $this->escape($node->method() . '()');
                $lines[] = "    {$nid}[\"{$nodeLabel}\"]";
            }
            $lines[] = '  end';
        }

        // Cross-component edges, deduped
        /** @var array<string, string> nodeId => class() */
        $nodeClassMap = [];
        foreach ($nodes as $node) {
            $nodeClassMap[$node->id()] = $node->class();
        }
        $edgesSeen = [];
        foreach ($flow->edges() as $edge) {
            if (!in_array($edge->type(), ['method_call', 'static_call', 'instantiation', 'wp_hook', 'import_call'], true)) {
                continue;
            }
            if (!isset($nodeClassMap[$edge->from()]) || !isset($nodeClassMap[$edge->to()])) {
                continue;
            }
            $fromComp = $this->componentName($nodeClassMap[$edge->from()]);
            $toComp   = $this->componentName($nodeClassMap[$edge->to()]);
            if ($fromComp === $toComp) {
                continue;
            }
            $key = $fromComp . "\0" . $toComp;
            if (isset($edgesSeen[$key])) {
                continue;
            }
            $edgesSeen[$key] = true;
            $lines[] = '  ' . $this->componentId($fromComp) . ' --> ' . $this->componentId($toComp);
        }

        return implode("\n", $lines);
    }

    /**
     * Generates a C4 Context diagram (Level 1) for a single project.
     *
     * Shows: the system itself, the actors that interact with it (HTTP clients,
     * CLI users), and any external systems it calls out to (resolved from
     * unresolved http_call edges pointing to absolute URLs).
     *
     * @param Flow   $flow        Analyzed flow for the project
     * @param string $projectName Human-readable project name (e.g. basename of root)
     * @return string Mermaid C4Context source
     */
    public function c4Context(Flow $flow, string $projectName): string
    {
        $nodeIds = array_fill_keys(
            array_map(fn(Node $n) => $n->id(), $flow->nodes()),
            true
        );

        // Scan nodes for exposed interfaces and detected languages
        $hasHttpApi  = false;
        $hasCli      = false;
        $languages   = [];

        foreach ($flow->nodes() as $node) {
            $lang = $node->language();
            if ($lang !== '') {
                $languages[$lang] = true;
            }

            // TypeScript Next.js route handlers: function name is an HTTP verb
            $httpVerbs = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];
            if ($node->language() === 'typescript' && in_array(strtoupper($node->method()), $httpVerbs, true)) {
                $hasHttpApi = true;
            }

            $meta = $node->metadata();
            if ($meta === null) {
                continue;
            }

            if (isset($meta['http_method']) || ($meta['entrypoint_type'] ?? null) === 'http') {
                $hasHttpApi = true;
            }
            if (($meta['entrypoint_type'] ?? null) === 'cli') {
                $hasCli = true;
            }
        }

        // Scan unresolved http_call edges for external hostnames
        $externalHosts = []; // hostname => true
        foreach ($flow->edges() as $edge) {
            if ($edge->type() !== 'http_call') {
                continue;
            }
            if (isset($nodeIds[$edge->to()])) {
                continue; // already resolved to an internal node
            }
            // Virtual ID format: "http:METHOD:url"
            $parts = explode(':', $edge->to(), 3);
            $url   = $parts[2] ?? '';
            if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
                continue; // relative path — internal route
            }
            $parsed = parse_url($url);
            $host   = (string) ($parsed['host'] ?? '');
            if ($host !== '') {
                $externalHosts[$host] = true;
            }
        }

        $langList  = implode(', ', array_filter(array_keys($languages)));
        $techDesc  = ($langList !== '' ? $langList . ' · ' : '')
                   . $flow->nodeCount() . ' nodes';
        $sysId     = 'sys_' . $this->c4Id($projectName);
        $boundId   = 'bound_' . $this->c4Id($projectName);
        $projEsc   = $this->escape($projectName);
        $techEsc   = $this->escape($techDesc);

        $lines   = [];
        $lines[] = 'C4Context';
        $lines[] = '  title System Context — ' . $this->escape($projectName);
        $lines[] = '';

        // Actors
        if ($hasHttpApi) {
            $lines[] = '  Person(httpClient, "HTTP Client", "Sends HTTP requests to the API")';
        }
        if ($hasCli) {
            $lines[] = '  Person(cliUser, "CLI User", "Runs CLI commands")';
        }

        // System boundary
        $lines[] = '';
        $lines[] = "  System_Boundary({$boundId}, \"{$projEsc}\") {";
        $lines[] = "    System({$sysId}, \"{$projEsc}\", \"{$techEsc}\")";
        $lines[] = '  }';

        // External systems
        foreach (array_keys($externalHosts) as $host) {
            $extId  = 'ext_' . $this->c4Id($host);
            $extEsc = $this->escape($host);
            $lines[] = '';
            $lines[] = "  System_Ext({$extId}, \"{$extEsc}\", \"External HTTP service\")";
        }

        // Relationships
        $lines[] = '';
        if ($hasHttpApi) {
            $lines[] = "  Rel(httpClient, {$sysId}, \"HTTP requests\")";
        }
        if ($hasCli) {
            $lines[] = "  Rel(cliUser, {$sysId}, \"CLI commands\")";
        }
        foreach (array_keys($externalHosts) as $host) {
            $extId  = 'ext_' . $this->c4Id($host);
            $lines[] = "  Rel({$sysId}, {$extId}, \"HTTP calls\")";
        }

        return implode("\n", $lines);
    }

    // --- helpers ---

    /** Safe identifier for C4 element IDs (no special chars, max 32 chars). */
    private function c4Id(string $s): string
    {
        return substr((string) (preg_replace('/[^A-Za-z0-9_]/', '_', $s) ?? 'x'), 0, 32);
    }

    private function nodeId(string $nodeId): string
    {
        return 'n' . substr(sha1($nodeId), 0, 12);
    }

    private function classId(string $fqcn): string
    {
        return preg_replace('/[^A-Za-z0-9]/', '_', $fqcn) ?? 'cls';
    }

    private function componentId(string $ns): string
    {
        return 'c_' . (preg_replace('/[^A-Za-z0-9]/', '_', $ns) ?? 'ns');
    }

    /**
     * Returns the top-2 namespace segments (component granularity).
     * e.g. "FlowEngine\Application\UseCase\Foo" → "FlowEngine\Application"
     * e.g. "App\Foo" → "App"
     */
    private function componentName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);
        return implode('\\', array_slice($parts, 0, min(2, count($parts))));
    }

    private function shortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);
        return (string) end($parts);
    }

    private function escape(string $s): string
    {
        return str_replace(['"', "\n", "\r"], ["'", '\\n', ' '], $s);
    }

    private function edgeArrowLabel(string $type): string
    {
        return match ($type) {
            'static_call'    => 'static',
            'instantiation'  => 'new',
            'wp_hook'        => 'hook',
            'function_call'  => 'fn',
            default          => '',
        };
    }
}
