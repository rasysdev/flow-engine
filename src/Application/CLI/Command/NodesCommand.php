<?php

namespace FlowEngine\Application\CLI\Command;

use FlowEngine\Bootstrap\Container;
use FlowEngine\Console\ConsoleIO;

final class NodesCommand implements Command
{
    public function __construct(
        private ConsoleIO $io
    ) {
    }

    public function supports(string $command): bool
    {
        return $command === 'nodes';
    }

    public function handle(array $argv): void
    {
        $projectPath = $argv[2] ?? null;

        if (!$projectPath) {
            $this->io->error('Usage: flow nodes <project_path>');
            return;
        }

        $container = new Container($projectPath);

        $nodes = $container->getNodes()->execute();

        $this->io->json([
            'nodes' => $nodes,
        ]);
    }
}
