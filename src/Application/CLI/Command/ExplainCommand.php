<?php

namespace FlowEngine\Application\CLI\Command;

use FlowEngine\Bootstrap\Container;
use FlowEngine\Console\ConsoleIO;
use LogicException;

final class ExplainCommand implements Command
{
    public function __construct(
        private ConsoleIO $io
    ) {
    }

    public function supports(string $command): bool
    {
        return $command === 'explain';
    }

    public function handle(array $argv): void
    {
        [$projectPath, $nodeId] = [$argv[2] ?? null, $argv[3] ?? null];

        if (!$projectPath || !$nodeId) {
            $this->io->error('Usage: flow explain <project_path> <Class::method>');
            return;
        }

        $container = new Container($projectPath);

        try {
            $node = $container->getNodeById()->execute($nodeId);

            if (!$node) {
                throw new LogicException("Node not found: {$nodeId}");
            }

            $explanation = $container
                ->explainNodeGovernance()
                ->execute($nodeId);

            $this->io->json([
                'node' => $explanation->nodeId,
                'finalDecision' => $explanation->finalDecision->name,
                'evaluation' => array_map(
                    fn($item) => [
                        'order' => $item->order,
                        'policy' => $item->policyClass,
                        'source' => $item->source->value,
                        'decision' => $item->decision->name,
                    ],
                    $explanation->items
                ),
            ]);
        } catch (LogicException $e) {
            $this->io->error($e->getMessage());
        }
    }
}
