<?php

namespace FlowEngine\Application\Service;

final class OrphanAuditService
{
    /** @var array<string, string[]> */
    private array $scopeRoots = [];

    /** @var array<string, string[]> */
    private array $scopeFiles = [];

    /** @var array<string, array{static: array<string, bool>, object: array<string, bool>}> */
    private array $scopeIndex = [];

    /**
     * @param string[]|null $externalRoots Optional external repositories to scan
     */
    public function __construct(
        private string $projectPath,
        ?array $externalRoots = null
    ) {
        $root = realpath($projectPath) ?: $projectPath;
        $this->scopeRoots = [
            'internal' => [$root . DIRECTORY_SEPARATOR . 'src'],
            'tests' => [$root . DIRECTORY_SEPARATOR . 'tests'],
            'docs' => [
                $root . DIRECTORY_SEPARATOR . 'docs',
                $root . DIRECTORY_SEPARATOR . 'README.md',
                $root . DIRECTORY_SEPARATOR . 'ROADMAP.md',
                $root . DIRECTORY_SEPARATOR . 'CHANGELOG.md',
            ],
            'external' => [],
        ];

        $roots = $externalRoots ?? [];
        foreach ($roots as $candidate) {
            $normalized = realpath($candidate) ?: $candidate;
            $this->scopeRoots['external'][] = $normalized;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $orphans
     * @return array{
     *   orphans: array<int, array<string, mixed>>,
     *   summary: array<string, mixed>,
     *   roots: array<string, string[]>
     * }
     */
    public function audit(array $orphans): array
    {
        $audited = [];
        $summary = [
            'total' => count($orphans),
            'abandonado' => 0,
            'falso_positivo' => 0,
            'investigar' => 0,
        ];

        foreach ($orphans as $orphan) {
            $nodeId = (string) ($orphan['nodeId'] ?? '');
            if ($nodeId === '') {
                $orphan['classification'] = 'investigar';
                $orphan['falsePositiveReason'] = null;
                $orphan['evidence'] = $this->emptyEvidence();
                $audited[] = $orphan;
                $summary['investigar']++;
                continue;
            }

            [$class, $method] = $this->splitNodeId($nodeId);
            $evidence = $this->collectEvidence($class, $method);
            [$classification, $reason] = $this->classify($orphan, $evidence);

            $orphan['classification'] = $classification;
            $orphan['falsePositiveReason'] = $reason;
            $orphan['evidence'] = $evidence;

            $audited[] = $orphan;
            $summary[$classification]++;
        }

        return [
            'orphans' => $audited,
            'summary' => $summary,
            'roots' => $this->scopeRoots,
        ];
    }

    /**
     * @return array{0:string,1:string}
     */
    private function splitNodeId(string $nodeId): array
    {
        if (!str_contains($nodeId, '::')) {
            return [$nodeId, ''];
        }

        $parts = explode('::', $nodeId, 2);
        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    /**
     * @return array{
     *   internalRefs:int,
     *   testRefs:int,
     *   docsRefs:int,
     *   externalRefs:int
     * }
     */
    private function collectEvidence(string $class, string $method): array
    {
        if ($method === '') {
            return $this->emptyEvidence();
        }

        $classShort = $this->shortClassName($class);

        return [
            'internalRefs' => $this->countScope('internal', $class, $classShort, $method),
            'testRefs' => $this->countScope('tests', $class, $classShort, $method),
            'docsRefs' => $this->countScope('docs', $class, $classShort, $method),
            'externalRefs' => $this->countScope('external', $class, $classShort, $method),
        ];
    }

    /**
     * @param array<string, mixed> $orphan
     * @param array<string, int> $evidence
     * @return array{0:string,1:?string}
     */
    private function classify(array $orphan, array $evidence): array
    {
        $sourceRefs = $evidence['internalRefs'] + $evidence['externalRefs'];
        $testRefs = $evidence['testRefs'];
        $docsRefs = $evidence['docsRefs'];
        $safe = (bool) ($orphan['safeToRemove'] ?? false);

        if ($sourceRefs > 0) {
            if ($evidence['externalRefs'] > 0) {
                return ['falso_positivo', 'Referenciado por consumidor externo.'];
            }

            return ['falso_positivo', 'Referenciado no codigo-fonte interno.'];
        }

        if ($safe && $testRefs === 0 && $docsRefs === 0) {
            return ['abandonado', null];
        }

        if ($testRefs > 0) {
            return ['investigar', 'Referenciado apenas em testes.'];
        }

        if ($docsRefs > 0) {
            return ['investigar', 'Referenciado em documentacao, sem evidencia de execucao.'];
        }

        return ['investigar', 'Sem evidencia suficiente para remocao automatica.'];
    }

    private function countScope(string $scope, string $class, string $classShort, string $method): int
    {
        $index = $this->indexForScope($scope);
        $hits = 0;

        if ($index['static'][$class . '::' . $method] ?? false) {
            $hits++;
        }
        if ($index['static'][$classShort . '::' . $method] ?? false) {
            $hits++;
        }
        if ($index['object'][$method] ?? false) {
            $hits++;
        }

        return $hits;
    }

    /**
     * @return array{static: array<string, bool>, object: array<string, bool>}
     */
    private function indexForScope(string $scope): array
    {
        if (isset($this->scopeIndex[$scope])) {
            return $this->scopeIndex[$scope];
        }

        $static = [];
        $object = [];

        foreach ($this->filesForScope($scope) as $file) {
            $content = @file_get_contents($file);
            if (!is_string($content) || $content === '') {
                continue;
            }

            if (preg_match_all('/([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)::\s*([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $content, $m)) {
                $classes = $m[1] ?? [];
                $methods = $m[2] ?? [];
                $count = min(count($classes), count($methods));
                for ($i = 0; $i < $count; $i++) {
                    $static[(string) $classes[$i] . '::' . (string) $methods[$i]] = true;
                }
            }

            if (preg_match_all('/->\s*([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $content, $mObj)) {
                foreach ($mObj[1] ?? [] as $method) {
                    $object[(string) $method] = true;
                }
            }
        }

        return $this->scopeIndex[$scope] = [
            'static' => $static,
            'object' => $object,
        ];
    }

    /**
     * @return string[]
     */
    private function filesForScope(string $scope): array
    {
        if (isset($this->scopeFiles[$scope])) {
            return $this->scopeFiles[$scope];
        }

        $files = [];
        foreach ($this->scopeRoots[$scope] ?? [] as $root) {
            if (is_file($root)) {
                $files[] = $root;
                continue;
            }

            if (!is_dir($root)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $entry) {
                $path = $entry->getPathname();
                if (!$entry->isFile() || $this->shouldSkipPath($path)) {
                    continue;
                }
                if (!$this->isTextLikeFile($path)) {
                    continue;
                }
                if (@filesize($path) !== false && filesize($path) > 2_000_000) {
                    continue;
                }
                $files[] = $path;
            }
        }

        return $this->scopeFiles[$scope] = $files;
    }

    private function isTextLikeFile(string $path): bool
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, ['php', 'ts', 'tsx', 'js', 'jsx', 'md', 'json', 'yaml', 'yml'], true)) {
            return true;
        }

        return basename($path) === 'package-lock.json';
    }

    private function shouldSkipPath(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);
        foreach (['/.git/', '/node_modules/', '/vendor/', '/dist/', '/build/', '/out/', '/coverage/'] as $token) {
            if (str_contains($normalized, $token)) {
                return true;
            }
        }

        return false;
    }

    private function shortClassName(string $class): string
    {
        $class = trim($class);
        if ($class === '') {
            return '';
        }

        $parts = preg_split('/[\\\\.:]/', $class);
        if (!is_array($parts) || $parts === []) {
            return $class;
        }

        return (string) end($parts);
    }

    /**
     * @return array<string, int>
     */
    private function emptyEvidence(): array
    {
        return [
            'internalRefs' => 0,
            'testRefs' => 0,
            'docsRefs' => 0,
            'externalRefs' => 0,
        ];
    }
}
