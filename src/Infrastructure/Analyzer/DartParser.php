<?php

namespace FlowEngine\Infrastructure\Analyzer;

use FlowEngine\Domain\Flow\Edge;
use FlowEngine\Domain\Flow\Node;
use FlowEngine\Domain\Flow\NodeFactory;

final class DartParser implements FileParser
{
    private ?string $packageName = null;

    /** @var array<string, string>|null */
    private ?array $apiConstants = null;

    /** @var string[]|null */
    private ?array $platforms = null;

    public function __construct(
        private readonly NodeFactory $nodeFactory,
        private readonly string $projectRoot
    ) {
    }

    /**
     * @return array{nodes: Node[], edges: Edge[]}
     */
    public function parse(string $file): array
    {
        $content = @file_get_contents($file);
        if ($content === false) {
            return ['nodes' => [], 'edges' => []];
        }

        $lines = preg_split("/\r\n|\n|\r/", $content);
        if (!is_array($lines)) {
            return ['nodes' => [], 'edges' => []];
        }

        $module = $this->moduleNameFromPath($file);
        $kind = $this->kindFromPath($file);
        $node = $this->nodeFactory->create(
            $module,
            'module',
            $file,
            1,
            'dart',
            $this->buildMetadata($file, $content, $lines, $module, $kind)
        );

        $edges = [];
        foreach ($this->collectLocalImports($lines, $file) as $resolvedModule) {
            $edges[] = new Edge(
                $node->id(),
                'dart:' . $resolvedModule . '::module',
                'import',
                'dart_import'
            );
        }

        foreach ($this->extractHttpCalls($lines, $node->id()) as $httpEdge) {
            $edges[] = $httpEdge;
        }

        return ['nodes' => [$node], 'edges' => $edges];
    }

    /**
     * @param string[] $lines
     * @return array<string, mixed>
     */
    private function buildMetadata(string $file, string $content, array $lines, string $module, string $kind): array
    {
        $metadata = [
            'kind' => $kind,
            'layer' => $this->layerFromKind($kind),
            'module' => $module,
            'framework' => 'flutter',
            'package' => $this->packageName(),
            'platforms' => $this->platforms(),
            'local_imports' => array_values($this->collectLocalImports($lines, $file)),
            'integrations' => $this->detectIntegrations($content),
        ];

        $symbol = $this->primarySymbol($content);
        if ($symbol !== null) {
            $metadata['symbol'] = $symbol;
        }

        if (in_array($kind, ['main', 'router', 'presentation-page', 'presentation-widget', 'bloc', 'test'], true)) {
            $metadata['entrypoint_type'] = 'ui';
        }

        if ($kind === 'datasource') {
            $metadata['entrypoint_type'] = 'http-client';
        }

        return $metadata;
    }

    /**
     * @param string[] $lines
     * @return string[]
     */
    private function collectLocalImports(array $lines, string $file): array
    {
        $imports = [];

        foreach ($lines as $line) {
            if (!preg_match('/^\s*import\s+[\'"]([^\'"]+)[\'"]\s*;/', $line, $match)) {
                continue;
            }

            $specifier = trim($match[1]);
            $resolved = $this->resolveImportSpecifier($file, $specifier);
            if ($resolved !== null) {
                $imports[] = $resolved;
            }
        }

        return array_values(array_unique($imports));
    }

    private function resolveImportSpecifier(string $currentFile, string $specifier): ?string
    {
        $packageName = $this->packageName();

        if ($packageName !== null && str_starts_with($specifier, 'package:' . $packageName . '/')) {
            $relative = substr($specifier, strlen('package:' . $packageName . '/'));
            $candidate = $this->normalizePath($this->projectRoot . '/lib/' . $relative);
            return $this->moduleNameFromResolvedPath($candidate);
        }

        if (str_starts_with($specifier, './') || str_starts_with($specifier, '../')) {
            $candidate = $this->normalizePath(dirname($currentFile) . '/' . $specifier);
            return $this->moduleNameFromResolvedPath($candidate);
        }

        return null;
    }

    /**
     * @param string[] $lines
     * @return Edge[]
     */
    private function extractHttpCalls(array $lines, string $fromNodeId): array
    {
        $edges = [];

        foreach ($lines as $line) {
            if (!preg_match_all('/\.\s*(get|post|put|patch|delete)\s*\(\s*([^,\)]+)/i', $line, $matches, \PREG_SET_ORDER)) {
                continue;
            }

            foreach ($matches as $match) {
                $method = strtoupper($match[1]);
                $target = $this->normalizeHttpTargetExpression(trim($match[2]));
                if ($target === null) {
                    continue;
                }

                $edges[] = new Edge(
                    $fromNodeId,
                    'http:' . $method . ':' . $target,
                    strtolower($match[1]),
                    'http_call'
                );
            }
        }

        return $edges;
    }

    private function normalizeHttpTargetExpression(string $expression): ?string
    {
        $expr = trim($expression);
        $expr = rtrim($expr, ';');

        $constants = $this->apiConstants();

        $expr = preg_replace_callback(
            '/\$\{ApiConstants\.([A-Za-z0-9_]+)\}|ApiConstants\.([A-Za-z0-9_]+)/',
            static function (array $matches) use ($constants): string {
                $key = $matches[1] !== '' ? $matches[1] : $matches[2];
                return $constants[$key] ?? $matches[0];
            },
            $expr
        ) ?? $expr;

        $expr = trim($expr, "\"'");
        if ($expr === '') {
            return null;
        }

        $expr = preg_replace('/\$\{[^}]+\}/', '{*}', $expr) ?? $expr;
        $expr = preg_replace('/\$[A-Za-z_][A-Za-z0-9_]*/', '{*}', $expr) ?? $expr;

        if (preg_match('/^https?:\/\//i', $expr)) {
            $parts = parse_url($expr);
            $path = (string) ($parts['path'] ?? '');
            if ($path === '') {
                return null;
            }
            return $path;
        }

        if (preg_match('/^\/[A-Za-z0-9_\/\-\{\}\.\*]+$/', $expr)) {
            return $expr;
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function detectIntegrations(string $content): array
    {
        $map = [
            'firebase' => ['firebase_core', 'firebase_messaging', 'firebase_analytics', 'firebase_crashlytics'],
            'revenuecat' => ['purchases_flutter', 'purchases_ui_flutter'],
            'spotify' => ['spotify', 'spotify_repository', 'spotify_service', 'google_sign_in', 'app_links'],
            'dio' => ['package:dio/'],
            'tflite' => ['tflite_flutter'],
            'hive' => ['hive', 'hive_flutter'],
            'go_router' => ['go_router'],
        ];

        $detected = [];
        foreach ($map as $label => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($content, $pattern)) {
                    $detected[] = $label;
                    break;
                }
            }
        }

        sort($detected);

        return $detected;
    }

    private function primarySymbol(string $content): ?string
    {
        if (preg_match('/class\s+([A-Za-z_][A-Za-z0-9_]*)/', $content, $match)) {
            return $match[1];
        }

        if (preg_match('/void\s+main\s*\(/', $content) === 1) {
            return 'main';
        }

        return null;
    }

    private function kindFromPath(string $file): string
    {
        $relative = $this->relativePath($file);

        return match (true) {
            $relative === 'lib/main.dart' => 'main',
            str_starts_with($relative, 'lib/presentation/pages/') => 'presentation-page',
            str_starts_with($relative, 'lib/presentation/widgets/') => 'presentation-widget',
            str_starts_with($relative, 'lib/presentation/bloc/') => 'bloc',
            str_starts_with($relative, 'lib/domain/usecases/') => 'usecase',
            str_starts_with($relative, 'lib/domain/repositories/') => 'domain-repository',
            str_starts_with($relative, 'lib/domain/entities/') => 'entity',
            str_starts_with($relative, 'lib/domain/ports/') => 'port',
            str_starts_with($relative, 'lib/data/repositories/') => 'data-repository',
            str_starts_with($relative, 'lib/data/datasources/') => 'datasource',
            str_starts_with($relative, 'lib/data/models/') => 'model',
            str_starts_with($relative, 'lib/core/routing/') => 'router',
            str_starts_with($relative, 'lib/core/di/') => 'di',
            str_starts_with($relative, 'lib/core/services/') => 'service',
            str_starts_with($relative, 'lib/core/constants/') => 'constants',
            str_starts_with($relative, 'integration_test/') || str_starts_with($relative, 'test/') => 'test',
            default => 'module',
        };
    }

    private function layerFromKind(string $kind): string
    {
        return match ($kind) {
            'usecase', 'presentation-page', 'presentation-widget', 'bloc', 'router' => 'Application',
            'domain-repository', 'entity', 'port' => 'Domain',
            default => 'Infrastructure',
        };
    }

    private function moduleNameFromResolvedPath(string $path): ?string
    {
        $normalized = str_replace(['\\', '/'], '/', $path);
        $root = str_replace(['\\', '/'], '/', rtrim($this->projectRoot, '/\\'));

        if (!str_ends_with($normalized, '.dart')) {
            $normalized .= '.dart';
        }

        if (!str_starts_with($normalized, $root . '/')) {
            return null;
        }

        if (!file_exists(str_replace('/', DIRECTORY_SEPARATOR, $normalized))) {
            return null;
        }

        return $this->moduleNameFromPath(str_replace('/', DIRECTORY_SEPARATOR, $normalized));
    }

    private function moduleNameFromPath(string $file): string
    {
        $relative = $this->relativePath($file);
        $relative = preg_replace('/\.dart$/', '', $relative) ?? $relative;

        return str_replace('/', '.', trim($relative, '/'));
    }

    private function relativePath(string $file): string
    {
        $root = str_replace(['\\', '/'], '/', rtrim($this->projectRoot, '/\\'));
        $normalizedFile = str_replace(['\\', '/'], '/', $file);

        if (str_starts_with($normalizedFile, $root . '/')) {
            return substr($normalizedFile, strlen($root . '/'));
        }

        return ltrim($normalizedFile, '/');
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $leading = str_starts_with($path, '/') ? '/' : '';
        $parts = explode('/', $path);
        $result = [];

        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($result);
                continue;
            }
            $result[] = $part;
        }

        return $leading . implode('/', $result);
    }

    private function packageName(): ?string
    {
        if ($this->packageName !== null) {
            return $this->packageName;
        }

        $pubspec = $this->projectRoot . DIRECTORY_SEPARATOR . 'pubspec.yaml';
        if (!is_file($pubspec)) {
            return null;
        }

        $content = @file_get_contents($pubspec);
        if (!is_string($content)) {
            return null;
        }

        if (preg_match('/^name:\s*([A-Za-z0-9_]+)/m', $content, $match)) {
            $this->packageName = $match[1];
        }

        return $this->packageName;
    }

    /**
     * @return array<string, string>
     */
    private function apiConstants(): array
    {
        if ($this->apiConstants !== null) {
            return $this->apiConstants;
        }

        $path = $this->projectRoot . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'constants' . DIRECTORY_SEPARATOR . 'api_constants.dart';
        if (!is_file($path)) {
            $this->apiConstants = [];
            return $this->apiConstants;
        }

        $content = @file_get_contents($path);
        if (!is_string($content)) {
            $this->apiConstants = [];
            return $this->apiConstants;
        }

        $baseUrl = '';
        if (preg_match('/static\s+const\s+String\s+baseUrl\s*=\s*String\.fromEnvironment\([^)]*defaultValue:\s*[\'"]([^\'"]+)[\'"]/s', $content, $match)) {
            $baseUrl = $match[1];
        }

        $constants = [];
        if (preg_match_all('/static\s+const\s+String\s+([A-Za-z0-9_]+)\s*=\s*[\'"]([^\'"]+)[\'"]\s*;/', $content, $matches, \PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $value = $match[2];
                if ($baseUrl !== '' && str_contains($value, '$baseUrl')) {
                    $value = str_replace('$baseUrl', $baseUrl, $value);
                }
                $constants[$match[1]] = $value;
            }
        }

        $normalized = [];
        foreach ($constants as $name => $value) {
            if (preg_match('/^https?:\/\//i', $value)) {
                $parts = parse_url($value);
                $normalized[$name] = (string) ($parts['path'] ?? '');
                continue;
            }
            $normalized[$name] = $value;
        }

        $this->apiConstants = $normalized;

        return $this->apiConstants;
    }

    /**
     * @return string[]
     */
    private function platforms(): array
    {
        if ($this->platforms !== null) {
            return $this->platforms;
        }

        $platforms = [];
        foreach (['android', 'ios', 'windows'] as $platform) {
            if (is_dir($this->projectRoot . DIRECTORY_SEPARATOR . $platform)) {
                $platforms[] = $platform;
            }
        }

        $this->platforms = $platforms;

        return $this->platforms;
    }
}
