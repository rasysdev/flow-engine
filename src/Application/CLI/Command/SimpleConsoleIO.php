<?php

namespace FlowEngine\Application\CLI\Command;

/**
 * Implementação simples de ConsoleIO.
 * 
 * Usa echo para output e readline para input.
 * 
 * @internal
 */
final class SimpleConsoleIO implements ConsoleIO
{
    /**
     * @internal
     */
    public function info(string $message): void
    {
        echo $message . "\n";
    }

    /**
     * @internal
     */
    public function error(string $message): void
    {
        echo "\033[31m" . $message . "\033[0m\n"; // Red
    }

    /**
     * @internal
     */
    public function success(string $message): void
    {
        echo "\033[32m" . $message . "\033[0m\n"; // Green
    }

    /**
     * @internal
     */
    public function warning(string $message): void
    {
        echo "\033[33m" . $message . "\033[0m\n"; // Yellow
    }

    /**
     * @internal
     */
    public function confirm(string $question): bool
    {
        echo $question . " (y/n): ";
        
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);
        
        $answer = trim(strtolower($line ?? ''));
        
        return in_array($answer, ['y', 'yes', 'sim', 's']);
    }

    /**
     * @internal
     */
    public function json(array $data): void
    {
        echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
    }
}