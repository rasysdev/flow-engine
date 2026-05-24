<?php

namespace FlowEngine\Infrastructure\Telemetry;

/**
 * Coletor de métricas de performance e estatísticas.
 * 
 * Rastreia:
 * - Tempo de análise
 * - Memória usada
 * - Arquivos processados
 * - Erros encontrados
 */
final class PerformanceMetrics
{
    private float $startTime;
    private int $startMemory;

    private int $filesProcessed = 0;
    private int $filesSkipped = 0;
    private int $parseErrors = 0;

    private array $timings = [];
    private array $errors = [];

    public function __construct()
    {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(true);
    }

    /**
     * Marca início de uma operação.
     */
    public function startOperation(string $name): void
    {
        $this->timings[$name] = ['start' => microtime(true)];
    }

    /**
     * Marca fim de uma operação.
     */
    public function endOperation(string $name): void
    {
        if (isset($this->timings[$name]['start'])) {
            $this->timings[$name]['end'] = microtime(true);
            $this->timings[$name]['duration'] =
                $this->timings[$name]['end'] - $this->timings[$name]['start'];
        }
    }

    /**
     * Incrementa contador de arquivo processado.
     */
    public function fileProcessed(): void
    {
        $this->filesProcessed++;
    }

    /**
     * Incrementa contador de arquivo pulado.
     */
    public function fileSkipped(string $reason = ''): void
    {
        $this->filesSkipped++;

        if ($reason) {
            $this->errors[] = [
                'type' => 'skipped',
                'reason' => $reason,
                'time' => microtime(true),
            ];
        }
    }

    /**
     * Registra erro de parsing.
     */
    public function parseError(string $file, string $error): void
    {
        $this->parseErrors++;

        $this->errors[] = [
            'type' => 'parse_error',
            'file' => $file,
            'error' => $error,
            'time' => microtime(true),
        ];
    }

    /**
     * Retorna relatório completo de métricas.
     */
    public function getReport(): array
    {
        $totalTime = microtime(true) - $this->startTime;
        $totalMemory = memory_get_usage(true) - $this->startMemory;
        $peakMemory = memory_get_peak_usage(true);

        return [
            'timing' => [
                'total' => round($totalTime, 3),
                'operations' => array_map(
                    fn($t) => round($t['duration'] ?? 0, 3),
                    $this->timings
                ),
            ],
            'memory' => [
                'used' => $this->formatBytes($totalMemory),
                'peak' => $this->formatBytes($peakMemory),
            ],
            'files' => [
                'processed' => $this->filesProcessed,
                'skipped' => $this->filesSkipped,
                'parse_errors' => $this->parseErrors,
                'total' => $this->filesProcessed + $this->filesSkipped,
            ],
            'errors' => $this->errors,
        ];
    }

    /**
     * Imprime relatório formatado.
     */
    public function printReport(): void
    {
        $report = $this->getReport();

        echo "\n";
        echo "⏱️  Performance Metrics:\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo sprintf("   Total Time: %.3fs\n", $report['timing']['total']);
        echo sprintf("   Memory Used: %s\n", $report['memory']['used']);
        echo sprintf("   Peak Memory: %s\n", $report['memory']['peak']);
        echo sprintf("   Files Processed: %d\n", $report['files']['processed']);
        echo sprintf("   Files Skipped: %d\n", $report['files']['skipped']);
        echo sprintf("   Parse Errors: %d\n", $report['files']['parse_errors']);

        if (!empty($report['timing']['operations'])) {
            echo "\n   Operation Timings:\n";
            foreach ($report['timing']['operations'] as $op => $time) {
                echo sprintf("     • %s: %.3fs\n", $op, $time);
            }
        }

        if ($report['files']['parse_errors'] > 0) {
            echo "\n⚠️  Parse Errors:\n";
            $parseErrors = array_filter($report['errors'], fn($e) => $e['type'] === 'parse_error');
            foreach (array_slice($parseErrors, 0, 5) as $error) {
                echo sprintf("   • %s\n", basename($error['file']));
                echo sprintf("     %s\n", $error['error']);
            }

            if (count($parseErrors) > 5) {
                $remaining = count($parseErrors) - 5;
                echo sprintf("   ... and %d more\n", $remaining);
            }
        }

        echo "\n";
    }

    /**
     * Formata bytes pra human-readable.
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}