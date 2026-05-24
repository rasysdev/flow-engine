<?php

namespace Tests\Unit\Application\DTO;

use FlowEngine\Application\DTO\SymbolDTO;
use PHPUnit\Framework\TestCase;

class SymbolDTOTest extends TestCase
{
    public function testMakeGeneratesCorrectId(): void
    {
        $dto = SymbolDTO::make('TriangleAlertIcon', 'import', '/app/Component.tsx', 5, 'lucide-react');

        $this->assertSame('import:/app/Component.tsx::TriangleAlertIcon', $dto->id);
    }

    public function testMakeSetsAllFields(): void
    {
        $dto = SymbolDTO::make('handleClick', 'export_function', '/app/utils.ts', 12);

        $this->assertSame('handleClick', $dto->name);
        $this->assertSame('export_function', $dto->kind);
        $this->assertSame('/app/utils.ts', $dto->file);
        $this->assertSame(12, $dto->line);
        $this->assertNull($dto->sourceModule);
    }

    public function testMakeWithSourceModule(): void
    {
        $dto = SymbolDTO::make('useState', 'import', '/app/Component.tsx', 1, 'react');

        $this->assertSame('react', $dto->sourceModule);
    }

    public function testToArray(): void
    {
        $dto = SymbolDTO::make('MAX_SIZE', 'const', '/app/config.php', 3);

        $arr = $dto->toArray();

        $this->assertSame('const:/app/config.php::MAX_SIZE', $arr['id']);
        $this->assertSame('MAX_SIZE', $arr['name']);
        $this->assertSame('const', $arr['kind']);
        $this->assertSame('/app/config.php', $arr['file']);
        $this->assertSame(3, $arr['line']);
        $this->assertNull($arr['sourceModule']);
    }

    public function testConstructorDirectly(): void
    {
        $dto = new SymbolDTO('myid', 'Foo', 'function', '/src/bar.php', 7, 'some-module');

        $this->assertSame('myid', $dto->id);
        $this->assertSame('Foo', $dto->name);
        $this->assertSame('function', $dto->kind);
        $this->assertSame('/src/bar.php', $dto->file);
        $this->assertSame(7, $dto->line);
        $this->assertSame('some-module', $dto->sourceModule);
    }
}
