<?php

namespace Tests\Application\CLI\Command;

use FlowEngine\Application\CLI\Command\RefactorValidateCommand;
use FlowEngine\Console\ConsoleIO;
use PHPUnit\Framework\TestCase;

final class RefactorValidateCommandTest extends TestCase
{
    public function test_supports_refactor_validate_command(): void
    {
        $io = $this->createStub(ConsoleIO::class);
        $command = new RefactorValidateCommand($io);

        $this->assertTrue($command->supports('refactor-validate'));
        $this->assertFalse($command->supports('refactor-execute'));
    }

    public function test_requires_project_path_plan_label_and_step(): void
    {
        $io = $this->createMock(ConsoleIO::class);
        $io->expects($this->once())
            ->method('error')
            ->with('Usage: flow refactor-validate <project_path> --plan=<label> --step=<N> [--format=json|markdown]');

        $command = new RefactorValidateCommand($io);
        $command->handle(['bin/engine.php', 'refactor-validate']);
    }

    public function test_rejects_invalid_format(): void
    {
        $io = $this->createMock(ConsoleIO::class);
        $io->expects($this->once())
            ->method('error')
            ->with('Invalid format. Use --format=json or --format=markdown');

        $command = new RefactorValidateCommand($io);
        $command->handle([
            'bin/engine.php',
            'refactor-validate',
            '.',
            '--plan=user-refactor',
            '--step=1',
            '--format=xml',
        ]);
    }
}

