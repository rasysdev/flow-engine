<?php

namespace Tests\Application\CLI\Command;

use FlowEngine\Application\CLI\Command\ArchitectureCommand;
use FlowEngine\Application\CLI\Command\ComplexityCommand;
use FlowEngine\Application\CLI\Command\CyclesCommand;
use FlowEngine\Application\CLI\Command\MetricsCommand;
use FlowEngine\Application\CLI\Command\OrphansCommand;
use FlowEngine\Console\ConsoleIO;
use PHPUnit\Framework\TestCase;

final class AnalysisCommandsTest extends TestCase
{
    public function test_complexity_command_supports_and_requires_path(): void
    {
        $io = $this->createMock(ConsoleIO::class);
        $io->expects($this->once())
            ->method('error')
            ->with('Usage: flow complexity <project_path> [--context]');

        $command = new ComplexityCommand($io);

        $this->assertTrue($command->supports('complexity'));
        $command->handle(['bin/engine.php', 'complexity']);
    }

    public function test_cycles_command_supports_and_requires_path(): void
    {
        $io = $this->createMock(ConsoleIO::class);
        $io->expects($this->once())
            ->method('error')
            ->with('Usage: flow cycles <project_path> [--context]');

        $command = new CyclesCommand($io);

        $this->assertTrue($command->supports('cycles'));
        $command->handle(['bin/engine.php', 'cycles']);
    }

    public function test_architecture_command_supports_and_requires_path(): void
    {
        $io = $this->createMock(ConsoleIO::class);
        $io->expects($this->once())
            ->method('error')
            ->with('Usage: flow architecture <project_path> [--context]');

        $command = new ArchitectureCommand($io);

        $this->assertTrue($command->supports('architecture'));
        $command->handle(['bin/engine.php', 'architecture']);
    }

    public function test_metrics_command_supports_and_requires_path(): void
    {
        $io = $this->createMock(ConsoleIO::class);
        $io->expects($this->once())
            ->method('error')
            ->with('Usage: flow metrics <project_path> [--context]');

        $command = new MetricsCommand($io);

        $this->assertTrue($command->supports('metrics'));
        $command->handle(['bin/engine.php', 'metrics']);
    }

    public function test_orphans_command_supports_and_requires_path(): void
    {
        $io = $this->createMock(ConsoleIO::class);
        $io->expects($this->once())
            ->method('error')
            ->with('Usage: flow orphans <project_path> [--context] [--audit]');

        $command = new OrphansCommand($io);

        $this->assertTrue($command->supports('orphans'));
        $command->handle(['bin/engine.php', 'orphans']);
    }
}
