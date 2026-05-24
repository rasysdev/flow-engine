<?php

namespace FlowEngine\Console;

class ConsoleIO
{
    private bool $hasErrors = false;

    public function info(string $message): void
    {
        echo "[INFO] {$message}\n";
    }

    public function error(string $message): void
    {
        $this->hasErrors = true;
        fwrite(STDERR, "[ERROR] {$message}\n");
    }

    public function writeln(string $message = ''): void
    {
        echo $message . "\n";
    }

    public function json(array $data): void
    {
        echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
    }

    public function hasErrors(): bool
    {
        return $this->hasErrors;
    }
}
