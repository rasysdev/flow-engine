<?php

namespace FlowEngine\Infrastructure\Analyzer;

use FlowEngine\Domain\Flow\Edge;
use FlowEngine\Domain\Flow\Node;

/**
 * CrossLanguageEdgeDetector
 *
 * Post-parse step: resolves virtual http_call edges (whose `to` is a virtual ID like
 * "http:GET:/api/users") to real node IDs by matching the URL against nodes that carry
 * http_path metadata (PHP, Go, Python, TypeScript route handlers).
 *
 * Algorithm:
 * 1. Build a route index from all nodes that have `http_path` metadata.
 * 2. For each `http_call` edge, extract the METHOD:path key and look up the index.
 * 3. If a match is found, append a new resolved edge (original virtual edge is kept).
 */
final class CrossLanguageEdgeDetector
{
    /**
     * @param Node[] $nodes
     * @param Edge[] $edges
     * @return Edge[] enriched edges (originals + resolved cross-language edges)
     */
    public function detect(array $nodes, array $edges): array
    {
        $routeIndex  = $this->buildRouteIndex($nodes);
        $importIndex = $this->buildImportIndex($nodes);

        if ($routeIndex === [] && $importIndex === []) {
            return $edges;
        }

        $resolved = [];

        foreach ($edges as $edge) {
            // HTTP call resolution (existing behaviour)
            if ($routeIndex !== [] && $edge->type() === 'http_call') {
                $to = $edge->to();
                if (str_starts_with($to, 'http:')) {
                    $key           = substr($to, strlen('http:'));
                    $matchedNodeId = $this->matchRoute($key, $routeIndex);
                    if ($matchedNodeId !== null && $matchedNodeId !== $edge->from()) {
                        $resolved[] = new Edge($edge->from(), $matchedNodeId, $edge->method(), 'http_call');
                    }
                }
            }

            // TypeScript/JavaScript import resolution
            if ($importIndex !== [] && $edge->type() === 'import_call') {
                $to = $edge->to();
                if (str_starts_with($to, 'ts_import:')) {
                    $key           = substr($to, strlen('ts_import:'));
                    $matchedNodeId = $importIndex[$key] ?? null;
                    if ($matchedNodeId !== null && $matchedNodeId !== $edge->from()) {
                        $resolved[] = new Edge($edge->from(), $matchedNodeId, $edge->method(), 'import_call');
                    }
                }
            }
        }

        return array_merge($edges, $resolved);
    }

    /**
     * Build route index: "METHOD:/path" => nodeId
     *
     * @param Node[] $nodes
     * @return array<string, string>
     */
    private function buildRouteIndex(array $nodes): array
    {
        $index = [];

        foreach ($nodes as $node) {
            $meta = $node->metadata();
            if ($meta === null) {
                continue;
            }

            $path   = $meta['http_path'] ?? null;
            $method = $meta['http_method'] ?? 'GET';

            if ($path === null || $path === '') {
                continue;
            }

            $key = strtoupper((string) $method) . ':' . $path;
            $index[$key] = $node->id();
        }

        return $index;
    }

    /**
     * Build import index for TypeScript/JavaScript nodes.
     *
     * For top-level exported functions/constants:
     *   key = "{modulePath}::{symbolName}"  →  nodeId
     *
     * For class methods (modulePart ends with an UpperCase segment = class name):
     *   key = "{parentModule}::{ClassName}"  →  first method's nodeId
     *
     * @param Node[] $nodes
     * @return array<string, string>
     */
    private function buildImportIndex(array $nodes): array
    {
        $index = [];

        foreach ($nodes as $node) {
            if (!in_array($node->language(), ['typescript', 'javascript'], true)) {
                continue;
            }

            $id = $node->id();

            // Node ID format: "{language}:{modulePart}::{methodName}"
            $colonPos = strpos($id, ':');
            if ($colonPos === false) {
                continue;
            }
            $rest = substr($id, $colonPos + 1);

            $dcolonPos = strpos($rest, '::');
            if ($dcolonPos === false) {
                continue;
            }

            $modulePart = substr($rest, 0, $dcolonPos); // e.g. "src.services.UserService"
            $methodName = substr($rest, $dcolonPos + 2); // e.g. "getUser"

            // Detect if the last segment of modulePart is a ClassName (starts uppercase)
            $dotPos = strrpos($modulePart, '.');
            if ($dotPos !== false) {
                $lastSegment = substr($modulePart, $dotPos + 1);
                if ($lastSegment !== '' && ctype_upper($lastSegment[0])) {
                    // Class: index by "{parentModule}::{ClassName}" → first method encountered
                    $parentModule = substr($modulePart, 0, $dotPos);
                    $classKey     = $parentModule . '::' . $lastSegment;
                    if (!isset($index[$classKey])) {
                        $index[$classKey] = $id;
                    }
                    continue;
                }
            }

            // Top-level function/const: index by "{modulePart}::{methodName}"
            $index[$modulePart . '::' . $methodName] = $id;
        }

        return $index;
    }

    /**
     * Match a "METHOD:/path" key against the route index.
     * Supports exact match and {param} wildcard matching.
     *
     * @param array<string, string> $routeIndex
     */
    private function matchRoute(string $key, array $routeIndex): ?string
    {
        // Exact match
        if (isset($routeIndex[$key])) {
            return $routeIndex[$key];
        }

        // Wildcard match: replace {param} in route templates with regex [^/]+
        foreach ($routeIndex as $routeKey => $nodeId) {
            if (!str_contains($routeKey, '{')) {
                continue;
            }

            // Replace {param} placeholders BEFORE preg_quote so that the braces
            // are not escaped (preg_quote turns '{' into '\{', which breaks the
            // subsequent replacement).
            $parameterized = preg_replace('/\{[^}]+\}/', '__WILDCARD__', $routeKey);
            if ($parameterized === null) {
                continue;
            }
            $pattern = str_replace('__WILDCARD__', '[^/]+', preg_quote($parameterized, '#'));

            if (preg_match('#^' . $pattern . '$#', $key)) {
                return $nodeId;
            }
        }

        return null;
    }
}
