<?php

namespace FlowEngine\Application\CLI\Command;

use FlowEngine\Bootstrap\Container;
use FlowEngine\Console\ConsoleIO;
use LogicException;

final class InputsCommand implements Command
{
    public function __construct(
        private ConsoleIO $io
    ) {
    }

    public function supports(string $command): bool
    {
        return $command === 'inputs';
    }

    public function handle(array $argv): void
    {
        [$projectPath, $nodeId] = [$argv[2] ?? null, $argv[3] ?? null];

        if (!$projectPath || !$nodeId) {
            $this->io->error('Usage: flow inputs <project_path> <Class::method>');
            return;
        }

        $container = new Container($projectPath);

        try {
            $node = $container->getNodeById()->execute($nodeId);

            if (!$node) {
                throw new LogicException("Node not found: {$nodeId}");
            }

            $inputs = $container->getNodeInputs()->execute($node);

            $this->io->json([
                'node' => $nodeId,
                'inputs' => $inputs->inputs,
                'return' => $inputs->returnType,
            ]);
        } catch (LogicException $e) {
            $this->io->error($e->getMessage());
        }
    }
}
