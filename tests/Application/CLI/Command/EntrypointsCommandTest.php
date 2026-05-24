<?php

namespace Tests\Application\CLI\Command;

use FlowEngine\Application\CLI\Command\EntrypointsCommand;
use FlowEngine\Console\ConsoleIO;
use PHPUnit\Framework\TestCase;

final class EntrypointsCommandTest extends TestCase
{
    public function test_supports_entrypoints_command(): void
    {
        $io = $this->createStub(ConsoleIO::class);
        $command = new EntrypointsCommand($io);

        $this->assertTrue($command->supports('entrypoints'));
        $this->assertFalse($command->supports('context'));
    }

    public function test_requires_project_path(): void
    {
        $io = $this->createMock(ConsoleIO::class);
        $io->expects($this->once())
            ->method('error')
            ->with('Usage: flow entrypoints <project_path> [--mode=actionable|raw]');

        $command = new EntrypointsCommand($io);
        $command->handle(['bin/engine.php', 'entrypoints']);
    }

    public function test_rejects_invalid_mode(): void
    {
        $io = $this->createMock(ConsoleIO::class);
        $io->expects($this->once())
            ->method('error')
            ->with('Invalid mode. Use --mode=actionable or --mode=raw');

        $command = new EntrypointsCommand($io);
        $command->handle(['bin/engine.php', 'entrypoints', '.', '--mode=invalid']);
    }
}
