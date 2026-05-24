<?php

declare(strict_types=1);

namespace FlowEngine\Application\ProjectMap;

use FlowEngine\Domain\Analysis\MetricsAnalyzer;
use FlowEngine\Domain\Contracts\Flow as FlowContract;
use FlowEngine\Domain\Flow\Node;

/**
 * Finds nodes by substring query or query batch.
 *
 * Designed for the flow_find MCP tool: the caller knows an approximate
 * class/method name and wants to locate it without a full project map.
 */
final class ProjectFindBuilder
{
    private const VALID_TYPES = ['class', 'method', 'namespace', 'symbol'];

    /**
     * @param string|null $type  One of 'class', 'method', 'namespace', or null for all.
     * @return array<string, mixed>
     */
    public function findInProject(
        string $projectRoot,
        FlowContract $flow,
        string $query,
        ?string $type,
        int $limit
    ): array {
        $query = strtolower(trim($query));
        $limit = max(1, min(50, $limit));
        $metricsAnalyzer = new MetricsAnalyzer($flow);

        return $this->findQueryInProject($flow, $metricsAnalyzer, $query, $type, $limit);
    }

    /**
     * @param string[] $queries
     * @param string|null $type  One of 'class', 'method', 'namespace', or null for all.
     * @return array<string, mixed>
     */
    public function findManyInProject(
        string $projectRoot,
        FlowContract $flow,
        array $queries,
        ?string $type,
        int $limit
    ): array {
        $limit = max(1, min(50, $limit));
        $queries = array_values(array_unique(array_map(
            static fn(string $query): string => strtolower(trim($query)),
            $queries
        )));

        $metricsAnalyzer = new MetricsAnalyzer($flow);
        $results = [];
        $truncated = false;

        foreach ($queries as $query) {
            $result = $this->findQueryInProject($flow, $metricsAnalyzer, $query, $type, $limit);
            $results[] = $result;

            if ($result['truncated'] === true) {
                $truncated = true;
            }
        }

        return [
            'kind'           => 'node_find_batch',
            'queries'        => $queries,
            'type'           => $type,
            'limit'          => $limit,
            'totalRequested' => count($queries),
            'returned'       => count($results),
            'truncated'      => $truncated,
            'results'        => $results,
            'warnings'       => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function findQueryInProject(
        FlowContract $flow,
        MetricsAnalyzer $metricsAnalyzer,
        string $query,
        ?string $type,
        int $limit
    ): array {
        if ($query === '') {
            return [
                'kind'      => 'node_find',
                'query'     => '',
                'type'      => $type,
                'matches'   => [],
                'truncated' => false,
                'symbols'   => [],
            ];
        }

        /** @var array<string, array{type: string, id: string, file: string, methods: string[], fan_in: int}> $groups */
        $groups = [];
        $matchCount = 0;
        $truncated = false;

        foreach ($flow->nodes() as $node) {
            $matched = $this->nodeMatchesQuery($node, $query, $type);
            if (!$matched) {
                continue;
            }

            $classKey = $node->class();

            if (!isset($groups[$classKey])) {
                if ($matchCount >= $limit) {
                    $truncated = true;
                    break;
                }
                $groups[$classKey] = [
                    'type'      => 'class',
                    'id'        => $node->class(),
                    'file'      => $node->file(),
                    'methods'   => [],
                    'fan_in'    => 0,
                    'signature' => $this->extractSignature($node->file(), $node->line()),
                ];
                $matchCount++;
            }

            $method = $node->method();
            if (!in_array($method, $groups[$classKey]['methods'], true)) {
                $groups[$classKey]['methods'][] = $method;
            }

            $fanIn = $metricsAnalyzer->metricsFor($node->id())->fanIn;
            if ($fanIn > $groups[$classKey]['fan_in']) {
                $groups[$classKey]['fan_in'] = $fanIn;
            }
        }

        $matches = array_values($groups);

        // Symbols are searched alongside nodes whenever the type filter doesn't
        // exclude them. This avoids hiding imports/top-level symbols (eg. `TriangleAlertIcon`)
        // behind unrelated class/method matches that share a substring.
        $symbolMatches = [];
        if ($type === 'symbol' || $type === null) {
            $symbolLimit = ($type === 'symbol') ? $limit : min(10, $limit);
            foreach ($flow->symbols()->findByName($query, $symbolLimit) as $sym) {
                $symbolMatches[] = $sym->toArray();
            }
        }

        return [
            'kind'          => 'node_find',
            'query'         => $query,
            'type'          => $type,
            'matches'       => $matches,
            'truncated'     => $truncated,
            'symbols'       => $symbolMatches,
        ];
    }

    private function nodeMatchesQuery(Node $node, string $query, ?string $type): bool
    {
        if ($type !== null && !in_array($type, self::VALID_TYPES, true)) {
            return false;
        }

        // Symbol-only search: nodes are not relevant
        if ($type === 'symbol') {
            return false;
        }

        $class  = strtolower($node->class());
        $method = strtolower($node->method());

        if ($type === null || $type === 'class') {
            if (str_contains($class, $query)) {
                return true;
            }
        }

        if ($type === null || $type === 'method') {
            if (str_contains($method, $query)) {
                return true;
            }
        }

        if ($type === null || $type === 'namespace') {
            // Namespace = everything before the last backslash in the class name
            $nsLower = strtolower($this->extractNamespace($node->class()));
            if ($nsLower !== '' && str_contains($nsLower, $query)) {
                return true;
            }
        }

        return false;
    }

    private function extractSignature(string $file, ?int $line): ?string
    {
        if ($line === null || $line < 1) {
            return null;
        }

        if (!is_file($file) || !is_readable($file)) {
            return null;
        }

        $handle = @fopen($file, 'r');
        if ($handle === false) {
            return null;
        }

        $current = 0;
        $result = null;
        while (($text = fgets($handle)) !== false) {
            $current++;
            if ($current === $line) {
                $result = trim($text);
                break;
            }
        }
        fclose($handle);

        return $result !== '' ? $result : null;
    }

    private function extractNamespace(string $fqcn): string
    {
        $lastBackslash = strrpos($fqcn, '\\');
        if ($lastBackslash === false) {
            return '';
        }

        return substr($fqcn, 0, $lastBackslash);
    }
}
