<?php

namespace FlowEngine\Application\CLI;

use FlowEngine\Application\CLI\Command\Command;
use FlowEngine\Console\ConsoleIO;

final class CommandRouter
{
    /** @var Command[] */
    private array $commands = [];

    public function __construct(
        private ConsoleIO $io,
        array $commands = []
    ) {
        $this->commands = $commands;
    }

    public function register(Command $command): void
    {
        $this->commands[] = $command;
    }

    public function handle(array $argv): void
    {
        $command = $argv[1] ?? null;

        if (!$command) {
            $this->io->error('No command provided');
            return;
        }

        foreach ($this->commands as $handler) {
            if ($handler->supports($command)) {
                $handler->handle($argv);
                return;
            }
        }

        $this->io->error("Unknown command: {$command}");
    }
}
