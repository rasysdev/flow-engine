<?php

namespace Tests\Application\CLI\Command;

use FlowEngine\Application\CLI\Command\RemediationProposalsCommand;
use FlowEngine\Console\ConsoleIO;
use PHPUnit\Framework\TestCase;

final class RemediationProposalsCommandTest extends TestCase
{
    public function test_supports_remediation_proposals_command(): void
    {
        $io = $this->createStub(ConsoleIO::class);
        $command = new RemediationProposalsCommand($io);

        $this->assertTrue($command->supports('remediation-proposals'));
        $this->assertFalse($command->supports('metrics'));
    }

    public function test_requires_project_path(): void
    {
        $io = $this->createMock(ConsoleIO::class);
        $io->expects($this->once())
            ->method('error')
            ->with('Usage: flow remediation-proposals <project_path> [--max=<N>] [--format=json|markdown] [--save=<label>]');

        $command = new RemediationProposalsCommand($io);
        $command->handle(['bin/engine.php', 'remediation-proposals']);
    }

    public function test_rejects_invalid_format(): void
    {
        $io = $this->createMock(ConsoleIO::class);
        $io->expects($this->once())
            ->method('error')
            ->with('Invalid format. Use --format=json or --format=markdown');

        $command = new RemediationProposalsCommand($io);
        $command->handle(['bin/engine.php', 'remediation-proposals', '.', '--format=xml']);
    }

    public function test_rejects_invalid_max(): void
    {
        $io = $this->createMock(ConsoleIO::class);
        $io->expects($this->once())
            ->method('error')
            ->with('Invalid max value. Use --max=<N> with N >= 1');

        $command = new RemediationProposalsCommand($io);
        $command->handle(['bin/engine.php', 'remediation-proposals', '.', '--max=0']);
    }
}
