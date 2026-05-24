<?php

/**
 * Autoloader simplificado para fixture de teste.
 * 
 * Simula comportamento do Composer autoload PSR-4.
 */

spl_autoload_register(function (string $class): void {
    // Namespace base: App\
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../App/src/';  // ← AJUSTADO

    // Verifica se a classe usa o namespace App\
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Remove o prefixo do namespace
    $relativeClass = substr($class, $len);

    // Converte namespace em caminho de arquivo
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    // Carrega o arquivo se existir
    if (file_exists($file)) {
        require $file;
    }
});