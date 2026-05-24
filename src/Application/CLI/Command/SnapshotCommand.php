<?php

namespace FlowEngine\Application\CLI\Command;

use FlowEngine\Bootstrap\Container;
use FlowEngine\Console\ConsoleIO;

final class SnapshotCommand implements Command
{
    public function __construct(
        private ConsoleIO $io
    ) {
    }

    public function supports(string $command): bool
    {
        return $command === 'snapshot';
    }

    public function handle(array $argv): void
    {
        $projectPath = $argv[2] ?? null;

        if (!$projectPath) {
            $this->io->error('Usage: flow snapshot <project_path> --save=<label> | --compare=<label> | --list');
            return;
        }

        $saveLabel = $this->extractOption($argv, '--save');
        $compareLabel = $this->extractOption($argv, '--compare');
        $isList = in_array('--list', $argv, true);

        if (!$saveLabel && !$compareLabel && !$isList) {
            $this->io->error('Usage: flow snapshot <project_path> --save=<label> | --compare=<label> | --list');
            return;
        }

        $container = new Container($projectPath);
        $container->analyzeProject()->execute();

        if ($isList) {
            $this->handleList($container);
            return;
        }

        if ($saveLabel) {
            $this->handleSave($container, $saveLabel);
            return;
        }

        if ($compareLabel) {
            $this->handleCompare($container, $compareLabel);
        }
    }

    private function handleSave(Container $container, string $label): void
    {
        $container->saveSnapshot()->execute($label);

        $this->io->json([
            'status' => 'saved',
            'label' => $label,
        ]);
    }

    private function handleCompare(Container $container, string $label): void
    {
        $result = $container->compareSnapshots()->execute($label);
        $this->io->json($result->toArray());
    }

    private function handleList(Container $container): void
    {
        $this->io->json(['snapshots' => $container->snapshotStore()->list()]);
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
