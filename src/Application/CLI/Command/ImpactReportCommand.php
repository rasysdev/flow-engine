<?php

namespace FlowEngine\Application\CLI\Command;

use FlowEngine\Bootstrap\Container;
use FlowEngine\Console\ConsoleIO;

final class ImpactReportCommand implements Command
{
    public function __construct(
        private ConsoleIO $io
    ) {
    }

    public function supports(string $command): bool
    {
        return $command === 'impact-report';
    }

    public function handle(array $argv): void
    {
        $projectPath = $argv[2] ?? null;
        $nodeId = $this->extractOption($argv, '--node');

        if (!$projectPath || !$nodeId) {
            $this->io->error('Usage: flow impact-report <project_path> --node=<Class::method> [--interpret]');
            return;
        }

        $container = new Container($projectPath);
        $container->analyzeProject()->execute();

        $report = $container->assessNodeImpact()->execute($nodeId);

        if (in_array('--interpret', $argv, true)) {
            $interpretation = $container->interpretChangeImpact()->execute($nodeId);
            $this->io->json([
                'report' => $report->toArray(),
                'interpretation' => $interpretation->toArray(),
            ]);
            return;
        }

        $this->io->json($report->toArray());
    }

    private function extractOption(array $argv, string $prefix): ?string
    {
        foreach ($argv as $arg) {
            if (str_starts_with($arg, $prefix . '=')) {
                return substr($arg, strlen($prefix) + 1);
            }
        }

        return null;
    }
}
