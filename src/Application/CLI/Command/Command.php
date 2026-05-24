<?php

namespace FlowEngine\Application\CLI\Command;

interface Command
{
    /**
     * @param array<int, string> $argv
     */
    public function supports(string $command): bool;

    /**
     * @param array<int, string> $argv
     */
    public function handle(array $argv): void;
}
