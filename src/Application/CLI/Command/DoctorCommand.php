<?php

namespace FlowEngine\Application\CLI\Command;

use FlowEngine\Bootstrap\Container;

final class DoctorCommand implements Command
{
    public function __construct(
        private \FlowEngine\Console\ConsoleIO $io
    ) {
    }

    public function supports(string $command): bool
    {
        return $command === 'doctor';
    }

    public function handle(array $argv): void
    {
        $projectPath = $argv[2] ?? getcwd();
        $configPath = rtrim($projectPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'flow-engine.json';

        $checks = [];

        if (!file_exists($configPath)) {
            $checks[] = $this->check('config_exists', 'fail', 'flow-engine.json not found');
            $this->emitResult($checks);
            return;
        }

        try {
            $config = (new Container($projectPath))->projectConfig();
            $checks[] = $this->check('config_valid', 'ok', 'Configuration valid');

            $autoload = $config->autoloadPath();
            if ($autoload && !file_exists($projectPath . DIRECTORY_SEPARATOR . $autoload)) {
                $checks[] = $this->check('autoload_exists', 'warn', "Autoload not found: {$autoload}");
            } else {
                $checks[] = $this->check('autoload_exists', 'ok', $autoload ? 'Autoload found' : 'Autoload not configured');
            }

            foreach ($config->scanInclude() as $dir) {
                $path = $projectPath . DIRECTORY_SEPARATOR . $dir;
                $checks[] = $this->check(
                    "include_exists:{$dir}",
                    is_dir($path) ? 'ok' : 'warn',
                    is_dir($path) ? 'Include exists' : "Include missing: {$dir}"
                );
            }

            foreach ($config->scanExclude() as $dir) {
                $path = $projectPath . DIRECTORY_SEPARATOR . $dir;
                $checks[] = $this->check(
                    "exclude_exists:{$dir}",
                    is_dir($path) ? 'ok' : 'warn',
                    is_dir($path) ? 'Exclude exists' : "Exclude missing: {$dir}"
                );
            }

            $checks[] = $this->check('php_version', version_compare(PHP_VERSION, '8.2.0', '>=') ? 'ok' : 'warn', 'PHP ' . PHP_VERSION);
        } catch (\Throwable $e) {
            $checks[] = $this->check('config_valid', 'fail', $e->getMessage());
        }

        $this->emitResult($checks);
    }

    private function emitResult(array $checks): void
    {
        $summary = [
            'ok' => 0,
            'warn' => 0,
            'fail' => 0,
        ];

        foreach ($checks as $check) {
            $summary[$check['status']]++;
        }

        $status = $summary['fail'] > 0 ? 'error' : ($summary['warn'] > 0 ? 'warn' : 'ok');

        $this->io->json([
            'status' => $status,
            'summary' => $summary,
            'checks' => $checks,
        ]);
    }

    private function check(string $name, string $status, string $message): array
    {
        return [
            'name' => $name,
            'status' => $status,
            'message' => $message,
        ];
    }
}
