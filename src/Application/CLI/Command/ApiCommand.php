<?php

namespace FlowEngine\Application\CLI\Command;

use FlowEngine\Console\ConsoleIO;

final class ApiCommand implements Command
{
    public function __construct(
        private ConsoleIO $io
    ) {
    }

    public function supports(string $command): bool
    {
        return $command === 'api';
    }

    public function handle(array $argv): void
    {
        $projectPath = $argv[2] ?? null;

        if (!$projectPath) {
            $this->io->error('Usage: flow api <project_path> [--host=127.0.0.1] [--port=8080]');
            return;
        }

        $root = realpath($projectPath);
        if ($root === false || !is_dir($root)) {
            $this->io->error("Project path not found: {$projectPath}");
            return;
        }

        $host = $this->extractOption($argv, '--host') ?? '127.0.0.1';
        $port = $this->extractOption($argv, '--port') ?? '8080';

        if (!preg_match('/^\d+$/', $port)) {
            $this->io->error("Invalid port: {$port}");
            return;
        }

        $portNumber = (int) $port;
        if ($portNumber < 1 || $portNumber > 65535) {
            $this->io->error("Invalid port: {$port}");
            return;
        }

        $routerScript = realpath(__DIR__ . '/../../../../bin/api-router.php');
        $docRoot = realpath(__DIR__ . '/../../../../');

        if ($routerScript === false || $docRoot === false) {
            $this->io->error('Cannot locate API router script.');
            return;
        }

        putenv("FLOW_ENGINE_API_PROJECT={$root}");

        $address = "{$host}:{$port}";
        $command = sprintf(
            '%s -S %s -t %s %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($address),
            escapeshellarg($docRoot),
            escapeshellarg($routerScript)
        );

        $this->io->info("Flow Engine API listening on http://{$address}");
        $this->io->info('Routes: /health, /api/v1/metrics, /api/v1/cycles, /api/v1/architecture, /api/v1/orphans, /api/v1/nodes, /api/v1/edges, /api/v1/flow, /api/v1/snapshots, /api/v1/context, /api/v1/bugs, /api/v1/appmap, /api/v1/appmap-diff, /api/v1/compliance-monitor, /api/v1/deployment-map, /api/v1/devops-map, /api/v1/website-map, /api/v1/diagram');

        passthru($command, $exitCode);

        if ($exitCode !== 0) {
            $this->io->error("API server stopped with exit code {$exitCode}");
        }
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
