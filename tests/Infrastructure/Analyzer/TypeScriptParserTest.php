<?php

namespace Tests\Infrastructure\Analyzer;

use FlowEngine\Domain\Flow\DefaultNodeFactory;
use FlowEngine\Infrastructure\Analyzer\TypeScriptParser;
use PHPUnit\Framework\TestCase;

final class TypeScriptParserTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/ts-parser-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function createTempFile(string $name, string $content): string
    {
        $path = $this->tempDir . '/' . $name;
        file_put_contents($path, $content);
        return $path;
    }

    private function makeParser(string $language = 'typescript'): TypeScriptParser
    {
        return new TypeScriptParser(new DefaultNodeFactory(), $this->tempDir, $language);
    }

    // -------------------------------------------------------------------------

    public function test_detects_class_with_methods(): void
    {
        $file = $this->createTempFile('user.service.ts', <<<'TS'
export class UserService {
    getUser(id: string): User {
        return this.repo.find(id);
    }

    createUser(data: CreateUserDto): User {
        return this.repo.save(data);
    }
}
TS);

        $result = $this->makeParser()->parse($file);

        $ids = array_map(fn($n) => $n->id(), $result['nodes']);
        self::assertContains('typescript:user.service.UserService::getUser', $ids);
        self::assertContains('typescript:user.service.UserService::createUser', $ids);
    }

    public function test_detects_exported_function(): void
    {
        $file = $this->createTempFile('helpers.ts', <<<'TS'
export function formatDate(date: Date): string {
    return date.toISOString();
}

export async function fetchData(url: string): Promise<any> {
    return fetch(url);
}
TS);

        $result = $this->makeParser()->parse($file);

        $ids = array_map(fn($n) => $n->id(), $result['nodes']);
        self::assertContains('typescript:helpers::formatDate', $ids);
        self::assertContains('typescript:helpers::fetchData', $ids);
    }

    public function test_detects_non_exported_main_function_as_cli_entrypoint(): void
    {
        $file = $this->createTempFile('server.ts', <<<'TS'
async function main(): Promise<void> {
    await startServer();
}
TS);

        $result = $this->makeParser()->parse($file);

        $node = $result['nodes'][0] ?? null;
        self::assertNotNull($node);
        self::assertSame('typescript:server::main', $node->id());
        self::assertSame('cli', $node->metadata()['entrypoint_type'] ?? null);
    }

    public function test_detects_route_get_export_as_http_entrypoint(): void
    {
        $file = $this->createTempFile('route.ts', <<<'TS'
export async function GET(): Promise<Response> {
    return new Response('ok');
}
TS);

        $result = $this->makeParser()->parse($file);

        $node = $result['nodes'][0] ?? null;
        self::assertNotNull($node);
        self::assertSame('typescript:route::GET', $node->id());
        self::assertSame('http', $node->metadata()['entrypoint_type'] ?? null);
        self::assertSame('GET', $node->metadata()['http_method'] ?? null);
    }

    public function test_nested_non_exported_function_does_not_clear_outer_edge_context(): void
    {
        $file = $this->createTempFile('server.ts', <<<'TS'
function main(): void {
    function inner(): void {}
    afterInner();
}

function afterInner(): void {}
TS);

        $result = $this->makeParser()->parse($file);

        $edgeTos = array_map(fn($e) => $e->to(), $result['edges']);
        self::assertContains('typescript:server::afterInner', $edgeTos);
    }

    public function test_node_id_includes_module_path(): void
    {
        $subDir = $this->tempDir . '/src/services';
        mkdir($subDir, 0777, true);
        $file = $subDir . '/auth.service.ts';
        file_put_contents($file, <<<'TS'
export class AuthService {
    login(username: string): void {}
}
TS);

        $result = $this->makeParser()->parse($file);

        $ids = array_map(fn($n) => $n->id(), $result['nodes']);
        self::assertContains('typescript:src.services.auth.service.AuthService::login', $ids);
    }

    public function test_stores_nestjs_controller_decorator_metadata(): void
    {
        $file = $this->createTempFile('users.controller.ts', <<<'TS'
@Controller('/users')
export class UsersController {
    getAll(): User[] {
        return [];
    }
}
TS);

        $result = $this->makeParser()->parse($file);

        $nodeMap = [];
        foreach ($result['nodes'] as $node) {
            $nodeMap[$node->id()] = $node;
        }

        $node = $nodeMap['typescript:users.controller.UsersController::getAll'] ?? null;
        self::assertNotNull($node, 'Node UsersController::getAll not found');
        self::assertNotNull($node->metadata());
        self::assertSame('nestjs', $node->metadata()['framework']);
    }

    public function test_stores_route_method_decorator_metadata(): void
    {
        $file = $this->createTempFile('api.controller.ts', <<<'TS'
@Controller('/api')
export class ApiController {
    @Get('/users')
    listUsers(): User[] {
        return [];
    }
}
TS);

        $result = $this->makeParser()->parse($file);

        $nodeMap = [];
        foreach ($result['nodes'] as $node) {
            $nodeMap[$node->id()] = $node;
        }

        $node = $nodeMap['typescript:api.controller.ApiController::listUsers'] ?? null;
        self::assertNotNull($node, 'Node ApiController::listUsers not found');
        self::assertNotNull($node->metadata());
        self::assertSame('GET', $node->metadata()['http_method']);
        self::assertSame('/api/users', $node->metadata()['http_path']);
    }

    public function test_detects_fetch_as_http_call_edge(): void
    {
        $file = $this->createTempFile('api.client.ts', <<<'TS'
export async function getUsers(): Promise<any> {
    return fetch('/api/users');
}
TS);

        $result = $this->makeParser()->parse($file);

        $edgeTos = array_map(fn($e) => $e->to(), $result['edges']);
        self::assertContains('http:GET:/api/users', $edgeTos);

        $httpEdges = array_filter($result['edges'], fn($e) => $e->type() === 'http_call');
        self::assertNotEmpty($httpEdges);
    }

    public function test_detects_axios_get_as_http_call_edge(): void
    {
        $file = $this->createTempFile('data.service.ts', <<<'TS'
export async function loadProducts(): Promise<any> {
    return axios.get('/api/products');
}
TS);

        $result = $this->makeParser()->parse($file);

        $edgeTos = array_map(fn($e) => $e->to(), $result['edges']);
        self::assertContains('http:GET:/api/products', $edgeTos);
    }

    public function test_javascript_file_gets_javascript_language_tag(): void
    {
        $file = $this->createTempFile('utils.js', <<<'JS'
export function helper() {
    return true;
}
JS);

        $result = $this->makeParser('javascript')->parse($file);

        self::assertNotEmpty($result['nodes']);
        self::assertSame('javascript', $result['nodes'][0]->language());
    }

    public function test_handles_empty_file(): void
    {
        $file = $this->createTempFile('empty.ts', '');

        $result = $this->makeParser()->parse($file);

        self::assertSame([], $result['nodes']);
        self::assertSame([], $result['edges']);
    }

    // ── Import tracking ──────────────────────────────────────────────────────

    public function test_detects_export_default_function(): void
    {
        $file = $this->createTempFile('page.tsx', <<<'TS'
export default function Page() {
    return null;
}
TS);

        $result = $this->makeParser()->parse($file);

        $ids = array_map(fn($n) => $n->id(), $result['nodes']);
        self::assertContains('typescript:page::Page', $ids);
    }

    public function test_emits_virtual_import_edge_for_named_import(): void
    {
        // Create the target file so we have a real module path reference
        $subDir = $this->tempDir . '/lib';
        mkdir($subDir, 0777, true);
        $file = $this->createTempFile('page.ts', <<<'TS'
import { formatDate } from './lib/utils';

export function render(): void {
    formatDate(new Date());
}
TS);

        $result = $this->makeParser()->parse($file);

        $importEdges = array_filter(
            $result['edges'],
            fn($e) => $e->type() === 'import_call'
        );
        self::assertNotEmpty($importEdges, 'Expected at least one import_call edge');

        $tos = array_map(fn($e) => $e->to(), array_values($importEdges));
        self::assertContains('ts_import:lib.utils::formatDate', $tos);
    }

    public function test_emits_virtual_import_edge_for_default_import(): void
    {
        $file = $this->createTempFile('app.ts', <<<'TS'
import Button from './components/Button';

export function render(): void {}
TS);

        $result = $this->makeParser()->parse($file);

        $importEdges = array_filter($result['edges'], fn($e) => $e->type() === 'import_call');
        $tos = array_map(fn($e) => $e->to(), array_values($importEdges));
        self::assertContains('ts_import:components.Button::Button', $tos);
    }

    public function test_skips_npm_package_imports(): void
    {
        $file = $this->createTempFile('widget.ts', <<<'TS'
import { useState } from 'react';
import { QueryClient } from '@tanstack/react-query';

export function Widget(): void {}
TS);

        $result = $this->makeParser()->parse($file);

        $importEdges = array_filter($result['edges'], fn($e) => $e->type() === 'import_call');
        self::assertEmpty($importEdges, 'npm package imports must not produce import_call edges');
    }

    public function test_skips_type_only_imports(): void
    {
        $file = $this->createTempFile('typed.ts', <<<'TS'
import type { User } from './types';
import { getUser } from './api';

export function load(): void {}
TS);

        $result = $this->makeParser()->parse($file);

        $importEdges = array_filter($result['edges'], fn($e) => $e->type() === 'import_call');
        $tos = array_map(fn($e) => $e->to(), array_values($importEdges));

        // type-only import must not appear
        self::assertNotContains('ts_import:types::User', $tos);
        // value import must appear
        self::assertContains('ts_import:api::getUser', $tos);
    }

    public function test_resolves_dotdot_relative_import(): void
    {
        // File is at tempDir/src/app/page.ts; imports from '../lib/utils'
        $srcDir = $this->tempDir . '/src/app';
        mkdir($srcDir, 0777, true);
        $file = $srcDir . '/page.ts';
        file_put_contents($file, <<<'TS'
import { helper } from '../lib/utils';

export function Page(): void {}
TS);

        $result = $this->makeParser()->parse($file);

        $importEdges = array_filter($result['edges'], fn($e) => $e->type() === 'import_call');
        $tos = array_map(fn($e) => $e->to(), array_values($importEdges));
        // ../lib/utils from src/app/ resolves to src/lib/utils
        self::assertContains('ts_import:src.lib.utils::helper', $tos);
    }

    public function test_resolves_at_slash_alias_import(): void
    {
        // @/ maps to {projectRoot}/src/
        $file = $this->createTempFile('admin.ts', <<<'TS'
import { apiClient } from '@/shared/lib/api-client';

export function Admin(): void {}
TS);

        $result = $this->makeParser()->parse($file);

        $importEdges = array_filter($result['edges'], fn($e) => $e->type() === 'import_call');
        $tos = array_map(fn($e) => $e->to(), array_values($importEdges));
        self::assertContains('ts_import:src.shared.lib.api-client::apiClient', $tos);
    }

    public function test_no_import_edges_when_file_has_no_nodes(): void
    {
        // Type-declaration-only file: has imports but no exported functions
        $file = $this->createTempFile('types.ts', <<<'TS'
import type { Config } from './config';

export type UserType = { id: string };
TS);

        $result = $this->makeParser()->parse($file);

        $importEdges = array_filter($result['edges'], fn($e) => $e->type() === 'import_call');
        self::assertEmpty($importEdges, 'No import edges without a from-node in the file');
    }

    public function test_method_endline_multiline_body(): void
    {
        $file = $this->createTempFile('multi.ts', <<<'TS'
export class Foo {
    bar(): void {
        const x = 1;
        return;
    }
}
TS);

        $result = $this->makeParser()->parse($file);

        $bar = null;
        foreach ($result['nodes'] as $node) {
            if ($node->method() === 'bar') {
                $bar = $node;
                break;
            }
        }

        self::assertNotNull($bar);
        $meta = $bar->metadata();
        self::assertIsArray($meta);
        self::assertArrayHasKey('endLine', $meta);
        self::assertSame(5, $meta['endLine']);
    }

    public function test_method_endline_inline_body(): void
    {
        $file = $this->createTempFile('inline.ts', <<<'TS'
export class Foo {
    bar(): number { return 1; }
}
TS);

        $result = $this->makeParser()->parse($file);

        $bar = null;
        foreach ($result['nodes'] as $node) {
            if ($node->method() === 'bar') {
                $bar = $node;
                break;
            }
        }

        self::assertNotNull($bar);
        $meta = $bar->metadata();
        self::assertIsArray($meta);
        self::assertArrayHasKey('endLine', $meta);
        self::assertSame(2, $meta['endLine']);
    }

    public function test_exported_function_endline(): void
    {
        $file = $this->createTempFile('export.ts', <<<'TS'
export function compute(x: number): number {
    const a = x + 1;
    return a;
}
TS);

        $result = $this->makeParser()->parse($file);

        $fn = null;
        foreach ($result['nodes'] as $node) {
            if ($node->method() === 'compute') {
                $fn = $node;
                break;
            }
        }

        self::assertNotNull($fn);
        $meta = $fn->metadata();
        self::assertIsArray($meta);
        self::assertArrayHasKey('endLine', $meta);
        self::assertSame(4, $meta['endLine']);
    }

    public function test_method_endline_multiline_signature(): void
    {
        $file = $this->createTempFile('multisig.ts', <<<'TS'
export class Foo {
    bar(
        arg: string,
        other: number,
    ): void {
        return;
    }
}
TS);

        $result = $this->makeParser()->parse($file);

        $bar = null;
        foreach ($result['nodes'] as $node) {
            if ($node->method() === 'bar') {
                $bar = $node;
                break;
            }
        }

        self::assertNotNull($bar);
        $meta = $bar->metadata();
        self::assertIsArray($meta);
        self::assertArrayHasKey('endLine', $meta);
        self::assertSame(7, $meta['endLine']);
    }

    public function test_function_endline_with_type_literal_in_signature(): void
    {
        $file = $this->createTempFile('typelit.ts', <<<'TS'
export function process(arg: { id: string },
                        other: number): void {
    return;
}
TS);

        $result = $this->makeParser()->parse($file);

        $fn = null;
        foreach ($result['nodes'] as $node) {
            if ($node->method() === 'process') {
                $fn = $node;
                break;
            }
        }

        self::assertNotNull($fn);
        $meta = $fn->metadata();
        self::assertIsArray($meta);
        self::assertArrayHasKey('endLine', $meta);
        self::assertSame(4, $meta['endLine']);
    }

    public function test_expression_bodied_arrow_endline(): void
    {
        $file = $this->createTempFile('arrow.ts', <<<'TS'
export const compute = (x: number) => x + 1;
TS);

        $result = $this->makeParser()->parse($file);

        $fn = null;
        foreach ($result['nodes'] as $node) {
            if ($node->method() === 'compute') {
                $fn = $node;
                break;
            }
        }

        self::assertNotNull($fn);
        $meta = $fn->metadata();
        self::assertIsArray($meta);
        self::assertArrayHasKey('endLine', $meta);
        self::assertSame(1, $meta['endLine']);
    }

    // ── Symbol collection ─────────────────────────────────────────────────────

    public function test_symbols_includes_npm_import(): void
    {
        $file = $this->createTempFile('widget.ts', <<<'TS'
import { TriangleAlertIcon, AlertCircle } from 'lucide-react';
import { useState } from 'react';

export function Widget(): void {}
TS);

        $result = $this->makeParser()->parse($file);

        $names = array_map(fn($s) => $s->name, $result['symbols']);
        self::assertContains('TriangleAlertIcon', $names);
        self::assertContains('AlertCircle', $names);
        self::assertContains('useState', $names);
    }

    public function test_symbols_npm_import_sets_source_module(): void
    {
        $file = $this->createTempFile('alert.ts', <<<'TS'
import { TriangleAlertIcon } from 'lucide-react';

export function Alert(): void {}
TS);

        $result = $this->makeParser()->parse($file);

        $sym = null;
        foreach ($result['symbols'] as $s) {
            if ($s->name === 'TriangleAlertIcon') {
                $sym = $s;
                break;
            }
        }

        self::assertNotNull($sym);
        self::assertSame('import', $sym->kind);
        self::assertSame('lucide-react', $sym->sourceModule);
    }

    public function test_symbols_import_uses_local_alias_name(): void
    {
        $file = $this->createTempFile('aliased.ts', <<<'TS'
import { Foo as Bar } from 'some-lib';

export function use(): void {}
TS);

        $result = $this->makeParser()->parse($file);

        $names = array_map(fn($s) => $s->name, $result['symbols']);
        self::assertContains('Bar', $names);
        self::assertNotContains('Foo', $names);
    }

    public function test_symbols_export_function_and_export_const(): void
    {
        $file = $this->createTempFile('exports.ts', <<<'TS'
export function formatDate(date: Date): string {
    return date.toISOString();
}

export const MAX_RETRY = 3;
TS);

        $result = $this->makeParser()->parse($file);

        $byName = [];
        foreach ($result['symbols'] as $s) {
            $byName[$s->name] = $s;
        }

        self::assertArrayHasKey('formatDate', $byName);
        self::assertSame('export_function', $byName['formatDate']->kind);
        self::assertArrayHasKey('MAX_RETRY', $byName);
        self::assertSame('export_const', $byName['MAX_RETRY']->kind);
    }

    public function test_symbols_type_only_imports_are_excluded(): void
    {
        $file = $this->createTempFile('typed.ts', <<<'TS'
import type { User } from './types';
import { getUser } from './api';

export function load(): void {}
TS);

        $result = $this->makeParser()->parse($file);

        $names = array_map(fn($s) => $s->name, $result['symbols']);
        self::assertNotContains('User', $names);
        self::assertContains('getUser', $names);
    }

    public function test_symbols_mixed_default_and_named_import(): void
    {
        $file = $this->createTempFile('mixed.ts', <<<'TS'
import React, { useState, useEffect } from 'react';
import Button, { ButtonProps } from './Button';

export function App(): void {}
TS);

        $result = $this->makeParser()->parse($file);

        $names = array_map(fn($s) => $s->name, $result['symbols']);
        self::assertContains('React', $names, 'default name from mixed import must be indexed');
        self::assertContains('useState', $names, 'named import must be indexed');
        self::assertContains('useEffect', $names, 'second named import must be indexed');
        self::assertContains('Button', $names, 'default name from local mixed import must be indexed');
        self::assertContains('ButtonProps', $names, 'named import from local mixed must be indexed');
    }
}
