<?php

namespace Tests\Application\CLI\Command;

use FlowEngine\Application\CLI\Command\RemediationStatusCommand;
use FlowEngine\Console\ConsoleIO;
use PHPUnit\Framework\TestCase;

final class RemediationStatusCommandTest extends TestCase
{
    public function test_supports_remediation_status_command(): void
    {
        $io = $this->createStub(ConsoleIO::class);
        $command = new RemediationStatusCommand($io);

        $this->assertTrue($command->supports('remediation-status'));
        $this->assertFalse($command->supports('metrics'));
    }

    public function test_requires_project_path_and_plan_label(): void
    {
        $io = $this->createMock(ConsoleIO::class);
        $io->expects($this->once())
            ->method('error')
            ->with('Usage: flow remediation-status <project_path> --plan=<label> [--format=json|markdown]');

        $command = new RemediationStatusCommand($io);
        $command->handle(['bin/engine.php', 'remediation-status']);
    }

    public function test_rejects_invalid_format(): void
    {
        $io = $this->createMock(ConsoleIO::class);
        $io->expects($this->once())
            ->method('error')
            ->with('Invalid format. Use --format=json or --format=markdown');

        $command = new RemediationStatusCommand($io);
        $command->handle([
            'bin/engine.php',
            'remediation-status',
            '.',
            '--plan=remediation',
            '--format=xml',
        ]);
    }
}

