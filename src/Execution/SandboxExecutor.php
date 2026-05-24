<?php

namespace FlowEngine\Execution;

class SandboxExecutor
{
    public function execute(
        string $projectPath,
        string $class,
        string $method,
        array $args = []
    ): array {
        $autoload = $projectPath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

        if (!file_exists($autoload)) {
            throw new \RuntimeException("Autoload not found in project.");
        }

        require_once $autoload;

        if (!class_exists($class)) {
            throw new \RuntimeException("Class {$class} not found.");
        }

        $instance = new $class();

        if (!method_exists($instance, $method)) {
            throw new \RuntimeException("Method {$method} not found in class {$class}.");
        }

        $start = microtime(true);

        try {
            $result = $instance->{$method}(...$args);
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }

        return [
            'status' => 'ok',
            'result' => $result,
            'time_ms' => round((microtime(true) - $start) * 1000, 2),
        ];
    }
}
