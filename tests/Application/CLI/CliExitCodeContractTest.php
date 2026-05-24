<?php

namespace Tests\Application\CLI;

use PHPUnit\Framework\TestCase;

final class CliExitCodeContractTest extends TestCase
{
    public function test_missing_arguments_return_non_zero_exit_code(): void
    {
        [$code] = $this->runCli(['bugs']);
        $this->assertSame(1, $code);
    }

    public function test_missing_required_option_return_non_zero_exit_code(): void
    {
        [$code] = $this->runCli(['impact-report', '.']);
        $this->assertSame(1, $code);
    }

    public function test_invalid_option_value_return_non_zero_exit_code(): void
    {
        [$code] = $this->runCli(['remediation-proposals', '.', '--format=xml']);
        $this->assertSame(1, $code);
    }

    /**
     * @param array<int, string> $args
     * @return array{0:int,1:string,2:string}
     */
    private function runCli(array $args): array
    {
        $engine = realpath(__DIR__ . '/../../../bin/engine.php');
        self::assertNotFalse($engine);

        $parts = array_map(
            static fn(string $arg): string => escapeshellarg($arg),
            $args
        );
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($engine) . ' ' . implode(' ', $parts);

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, dirname($engine));
        self::assertIsResource($process);

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [$exitCode, $stdout, $stderr];
    }
}
