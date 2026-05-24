<?php

namespace FlowEngine\Application\CLI\Command;

/**
 * Interface para I/O do console.
 * 
 * Abstrai interações com o usuário (output, prompts, confirmações).
 * 
 * @internal
 */
interface ConsoleIO
{
    /**
     * Exibe mensagem informativa.
     * 
     * @internal
     */
    public function info(string $message): void;

    /**
     * Exibe mensagem de erro.
     * 
     * @internal
     */
    public function error(string $message): void;

    /**
     * Exibe mensagem de sucesso.
     * 
     * @internal
     */
    public function success(string $message): void;

    /**
     * Exibe mensagem de warning.
     * 
     * @internal
     */
    public function warning(string $message): void;

    /**
     * Solicita confirmação do usuário.
     * 
     * @internal
     */
    public function confirm(string $question): bool;

    /**
     * Exibe dados em formato JSON.
     * 
     * @internal
     */
    public function json(array $data): void;
}