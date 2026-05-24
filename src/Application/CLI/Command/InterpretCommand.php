<?php

namespace FlowEngine\Application\CLI\Command;

use FlowEngine\Bootstrap\Container;
use FlowEngine\Console\ConsoleIO;

final class InterpretCommand implements Command
{
    private const VALID_TYPES = ['cycles', 'hotspots', 'impact', 'violations'];

    public function __construct(
        private ConsoleIO $io
    ) {
    }

    public function supports(string $command): bool
    {
        return $command === 'interpret';
    }

    public function handle(array $argv): void
    {
        $projectPath = $argv[2] ?? null;
        $type = $this->extractType($argv);

        if (!$projectPath || !$type) {
            $this->io->error(
                'Usage: flow interpret <project_path> --type=<cycles|hotspots|impact|violations> [--node=<Class::method>]'
            );
            return;
        }

        if (!in_array($type, self::VALID_TYPES, true)) {
            $this->io->error(
                "Invalid type: {$type}. Valid types: " . implode(', ', self::VALID_TYPES)
            );
            return;
        }

        $container = new Container($projectPath);
        $container->analyzeProject()->execute();

        $result = match ($type) {
            'cycles' => $container->interpretCycles()->execute(),
            'hotspots' => $container->interpretHotspots()->execute(),
            'impact' => $this->handleImpact($container, $argv),
            'violations' => $container->interpretViolations()->execute(),
        };

        $this->io->json($result->toArray());
    }

    private function extractType(array $argv): ?string
    {
        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--type=')) {
                return substr($arg, 7);
            }
        }

        return null;
    }

    private function extractNode(array $argv): ?string
    {
        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--node=')) {
                return substr($arg, 7);
            }
        }

        return null;
    }

    private function handleImpact(Container $container, array $argv): \FlowEngine\Application\DTO\InterpretationResultDTO
    {
        $nodeId = $this->extractNode($argv);

        if (!$nodeId) {
            $this->io->error('Impact interpretation requires --node=<Class::method>');
            return new \FlowEngine\Application\DTO\InterpretationResultDTO(
                type: 'impact',
                interpretation: '',
                tokensUsed: 0,
                context: [],
                metadata: ['error' => 'Missing --node argument']
            );
        }

        return $container->interpretImpact()->execute($nodeId);
    }
}
