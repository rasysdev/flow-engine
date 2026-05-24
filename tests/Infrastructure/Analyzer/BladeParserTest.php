<?php

namespace Tests\Infrastructure\Analyzer;

use FlowEngine\Infrastructure\Analyzer\BladeParser;
use PHPUnit\Framework\TestCase;

final class BladeParserTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $root = realpath(__DIR__ . '/../Fixtures/ExampleProject');
        self::assertNotFalse($root);
        $this->root = $root;
    }

    public function test_it_extracts_wire_click_edges(): void
    {
        $file = $this->root . '/resources/views/livewire/backup/b2-manager.blade.php';
        self::assertFileExists($file);

        $parser = new BladeParser($this->root, 'App\\Http\\Livewire');
        $result = $parser->parse($file);

        self::assertSame([], $result['nodes']);
        self::assertNotEmpty($result['edges']);

        $edgePairs = array_map(
            fn($e) => [$e->from(), $e->to(), $e->type()],
            $result['edges']
        );

        // wire:click="refreshBackups"
        self::assertContains(
            ['blade:livewire.backup.b2-manager', 'App\\Http\\Livewire\\Backup\\B2Manager::refreshBackups', 'wire_action'],
            $edgePairs
        );

        // wire:click="deleteBackup({{ $backup->id }})"
        self::assertContains(
            ['blade:livewire.backup.b2-manager', 'App\\Http\\Livewire\\Backup\\B2Manager::deleteBackup', 'wire_action'],
            $edgePairs
        );

        // wire:submit.prevent="save"
        self::assertContains(
            ['blade:livewire.backup.b2-manager', 'App\\Http\\Livewire\\Backup\\B2Manager::save', 'wire_action'],
            $edgePairs
        );
    }

    public function test_it_deduplicates_edges(): void
    {
        $file = $this->root . '/resources/views/livewire/backup/b2-manager.blade.php';

        $parser = new BladeParser($this->root, 'App\\Http\\Livewire');
        $result = $parser->parse($file);

        // "refreshBackups" appears twice in the blade, should only create 1 edge
        $refreshEdges = array_filter(
            $result['edges'],
            fn($e) => str_ends_with($e->to(), '::refreshBackups')
        );

        self::assertCount(1, $refreshEdges);
    }

    public function test_it_ignores_non_livewire_blades(): void
    {
        // Create a temp file outside the livewire directory
        $tmpDir = sys_get_temp_dir() . '/blade-parser-test-' . uniqid();
        mkdir($tmpDir . '/resources/views', 0777, true);
        $file = $tmpDir . '/resources/views/admin.blade.php';
        file_put_contents($file, '<button wire:click="doSomething">Click</button>');

        $parser = new BladeParser($tmpDir, 'App\\Http\\Livewire');
        $result = $parser->parse($file);

        self::assertSame([], $result['edges']);

        // Cleanup
        unlink($file);
        rmdir($tmpDir . '/resources/views');
        rmdir($tmpDir . '/resources');
        rmdir($tmpDir);
    }
}
