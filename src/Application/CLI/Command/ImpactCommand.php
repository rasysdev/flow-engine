<?php

namespace FlowEngine\Application\CLI\Command;

use FlowEngine\Bootstrap\Container;
use FlowEngine\Console\ConsoleIO;
use LogicException;

final class ImpactCommand implements Command
{
    public function __construct(
        private ConsoleIO $io
    ) {
    }

    public function supports(string $command): bool
    {
        return $command === 'impact';
    }

    public function handle(array $argv): void
    {
        [$projectPath, $nodeId] = [$argv[2] ?? null, $argv[3] ?? null];

        if (!$projectPath || !$nodeId) {
            $this->io->error('Usage: flow impact <project_path> <Class::method>');
            return;
        }

        $container = new Container($projectPath);

        $impact = $container->analyzeImpact()->execute($nodeId);

        if (!$impact) {
            $this->io->error("Node not found: {$nodeId}");
            return;
        }

        $this->io->json($impact);
    }
}
