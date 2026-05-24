#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Unified quality gate for architecture and local API contract safety.
 *
 * Modes:
 *  - pr:   fail on new architecture violations vs baseline
 *  - main: fail on any architecture violation
 *
 * Also executes:
 *  - ReadOnlyApi contract test suite
 */

$options = parseArgs($argv);
$mode = $options['mode'] ?? 'main';
$baseline = $options['baseline'] ?? (getenv('FLOW_ENGINE_BASELINE_LABEL') ?: 'baseline-main');

if (!in_array($mode, ['pr', 'main'], true)) {
    fwrite(STDERR, "Invalid --mode. Use --mode=pr or --mode=main\n");
    exit(1);
}

if ($mode === 'pr' && trim((string) $baseline) === '') {
    fwrite(STDERR, "Missing baseline label for PR mode. Use --baseline=<label>.\n");
    exit(1);
}

$php = escapeshellarg(PHP_BINARY);
$commands = [];

if ($mode === 'pr') {
    $commands[] = [
        'label' => 'Architecture gate (PR: fail-on=new)',
        'command' => sprintf(
            '%s bin/engine.php architecture-gate . --fail-on=new --baseline=%s --format=json',
            $php,
            escapeshellarg($baseline)
        ),
    ];
} else {
    $commands[] = [
        'label' => 'Architecture gate (main: fail-on=any)',
        'command' => sprintf(
            '%s bin/engine.php architecture-gate . --fail-on=any --format=json',
            $php
        ),
    ];
}

$commands[] = [
    'label' => 'Read-only API contract tests',
    'command' => sprintf(
        '%s vendor/bin/phpunit tests/Application/Http/ReadOnlyApiTest.php',
        $php
    ),
];

foreach ($commands as $step) {
    fwrite(STDOUT, "\n==> {$step['label']}\n");
    passthru($step['command'], $exitCode);
    if ($exitCode !== 0) {
        fwrite(STDERR, "Step failed: {$step['label']}\n");
        exit($exitCode);
    }
}

fwrite(STDOUT, "\nQuality gate passed for mode: {$mode}\n");
exit(0);

/**
 * @return array<string, string|bool>
 */
function parseArgs(array $argv): array
{
    $result = [];
    foreach ($argv as $arg) {
        if (!str_starts_with((string) $arg, '--')) {
            continue;
        }

        $arg = substr((string) $arg, 2);
        if (str_contains($arg, '=')) {
            [$k, $v] = explode('=', $arg, 2);
            $result[$k] = $v;
            continue;
        }

        $result[$arg] = true;
    }

    return $result;
}
