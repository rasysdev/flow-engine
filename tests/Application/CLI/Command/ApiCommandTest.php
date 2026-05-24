<?php

namespace Tests\Application\CLI\Command;

use FlowEngine\Application\CLI\Command\ApiCommand;
use FlowEngine\Console\ConsoleIO;
use PHPUnit\Framework\TestCase;

final class ApiCommandTest extends TestCase
{
    public function test_api_command_supports_and_requires_path(): void
    {
        $io = $this->createMock(ConsoleIO::class);
        $io->expects($this->once())
            ->method('error')
            ->with('Usage: flow api <project_path> [--host=127.0.0.1] [--port=8080]');

        $command = new ApiCommand($io);

        $this->assertTrue($command->supports('api'));
        $command->handle(['bin/engine.php', 'api']);
    }

    public function test_api_command_validates_invalid_port(): void
    {
        $projectPath = __DIR__ . '/../../../Infrastructure/Fixtures/ExampleProject';

        $io = $this->createMock(ConsoleIO::class);
        $io->expects($this->once())
            ->method('error')
            ->with('Invalid port: abc');

        $command = new ApiCommand($io);
        $command->handle(['bin/engine.php', 'api', $projectPath, '--port=abc']);
    }
}
