<?php

namespace FlowEngine\Application\CLI\Command;

use FlowEngine\Application\DTO\NodeDTO;
use FlowEngine\Application\DTO\EdgeDTO;
use FlowEngine\Bootstrap\Container;
use FlowEngine\Console\ConsoleIO;

final class FlowCommand implements Command
{
    public function __construct(
        private ConsoleIO $io
    ) {
    }

    public function supports(string $command): bool
    {
        return $command === 'flow';
    }

    public function handle(array $argv): void
    {
        $projectPath = $argv[2] ?? null;

        if (!$projectPath) {
            $this->io->error('Usage: flow flow <project_path>');
            return;
        }

        $container = new Container($projectPath);
        $container->analyzeProject()->execute();

        $flow = $container->getFlow();

        $nodes = array_map(
            fn($n) => NodeDTO::fromNode($n)->toArray(),
            $flow->nodes()
        );

        $edges = array_map(
            fn($e) => EdgeDTO::fromEdge($e)->toArray(),
            $flow->edges()
        );

        $this->io->json([
            'nodes' => array_values($nodes),
            'edges' => array_values($edges),
            'nodeCount' => $flow->nodeCount(),
            'edgeCount' => $flow->edgeCount(),
        ]);
    }
}
