<?php

namespace Tests\Application\CLI\Command;

use FlowEngine\Application\CLI\Command\RemediationApproveCommand;
use FlowEngine\Console\ConsoleIO;
use PHPUnit\Framework\TestCase;

final class RemediationApproveCommandTest extends TestCase
{
    public function test_supports_remediation_approve_command(): void
    {
        $io = $this->createStub(ConsoleIO::class);
        $command = new RemediationApproveCommand($io);

        $this->assertTrue($command->supports('remediation-approve'));
        $this->assertFalse($command->supports('metrics'));
    }

    public function test_requires_project_path_plan_and_id(): void
    {
        $io = $this->createMock(ConsoleIO::class);
        $io->expects($this->once())
            ->method('error')
            ->with(
                'Usage: flow remediation-approve <project_path> --plan=<label> --id=<proposal_id> [--by=<name>] [--format=json|markdown]'
            );

        $command = new RemediationApproveCommand($io);
        $command->handle(['bin/engine.php', 'remediation-approve']);
    }

    public function test_rejects_invalid_format(): void
    {
        $io = $this->createMock(ConsoleIO::class);
        $io->expects($this->once())
            ->method('error')
            ->with('Invalid format. Use --format=json or --format=markdown');

        $command = new RemediationApproveCommand($io);
        $command->handle([
            'bin/engine.php',
            'remediation-approve',
            '.',
            '--plan=remediation',
            '--id=arch-001',
            '--format=xml',
        ]);
    }
}

