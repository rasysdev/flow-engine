<?php

namespace Tests\Domain\Flow;

use FlowEngine\Application\DTO\SymbolDTO;
use FlowEngine\Domain\Flow\SymbolIndex;
use PHPUnit\Framework\TestCase;

final class SymbolIndexTest extends TestCase
{
    private function makeSymbol(string $name, string $kind = 'import', ?string $source = null): SymbolDTO
    {
        return SymbolDTO::make($name, $kind, '/app/file.ts', 1, $source);
    }

    public function test_empty_index_returns_zero_count(): void
    {
        $index = new SymbolIndex();
        $this->assertSame(0, $index->count());
        $this->assertSame([], $index->all());
    }

    public function test_find_by_name_substring_case_insensitive(): void
    {
        $index = new SymbolIndex([
            $this->makeSymbol('TriangleAlertIcon'),
            $this->makeSymbol('AlertCircle'),
            $this->makeSymbol('useState'),
        ]);

        $results = $index->findByName('alert');

        $this->assertCount(2, $results);
        $names = array_map(fn($s) => $s->name, $results);
        $this->assertContains('TriangleAlertIcon', $names);
        $this->assertContains('AlertCircle', $names);
    }

    public function test_find_by_name_respects_limit(): void
    {
        $symbols = [];
        for ($i = 0; $i < 10; $i++) {
            $symbols[] = $this->makeSymbol("FooIcon{$i}");
        }
        $index = new SymbolIndex($symbols);

        $results = $index->findByName('foo', 3);

        $this->assertCount(3, $results);
    }

    public function test_find_exact_returns_case_insensitive_exact_match(): void
    {
        $index = new SymbolIndex([
            $this->makeSymbol('useState'),
            $this->makeSymbol('useStateExtra'),
        ]);

        $results = $index->findExact('usestate');

        $this->assertCount(1, $results);
        $this->assertSame('useState', $results[0]->name);
    }

    public function test_to_array_serializes_all_symbols(): void
    {
        $index = new SymbolIndex([
            $this->makeSymbol('Foo', 'import', 'react'),
        ]);

        $arr = $index->toArray();

        $this->assertCount(1, $arr);
        $this->assertSame('Foo', $arr[0]['name']);
        $this->assertSame('react', $arr[0]['sourceModule']);
    }
}
