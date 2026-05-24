<?php

namespace FlowEngine\Infrastructure\CLI;

/**
 * Progress indicator para operações longas.
 * 
 * Mostra progresso em tempo real no terminal.
 */
final class ProgressIndicator
{
    private int $total;
    private int $current = 0;
    private float $startTime;
    private ?string $currentFile = null;

    public function __construct(int $total)
    {
        $this->total = $total;
        $this->startTime = microtime(true);
    }

    /**
     * Avança o progresso.
     */
    public function advance(string $file = ''): void
    {
        $this->current++;
        $this->currentFile = $file;
        $this->render();
    }

    /**
     * Renderiza a barra de progresso.
     */
    private function render(): void
    {
        $percentage = $this->total > 0
            ? round(($this->current / $this->total) * 100)
            : 0;

        $elapsed = microtime(true) - $this->startTime;
        $rate = $this->current > 0 ? $elapsed / $this->current : 0;
        $eta = ($this->total - $this->current) * $rate;

        // Progress bar
        $barWidth = 40;
        $filled = round($barWidth * ($percentage / 100));
        $bar = str_repeat('█', $filled) . str_repeat('░', $barWidth - $filled);

        // Current file (truncate if too long)
        $file = $this->currentFile ? basename($this->currentFile) : '';
        if (strlen($file) > 50) {
            $file = substr($file, 0, 47) . '...';
        }

        // Format ETA
        $etaStr = $eta < 60
            ? sprintf('%ds', $eta)
            : sprintf('%dm %ds', floor($eta / 60), $eta % 60);

        // Output (using \r to overwrite previous line)
        echo sprintf(
            "\r  [%s] %3d%% (%d/%d) • %s • ETA: %s",
            $bar,
            $percentage,
            $this->current,
            $this->total,
            str_pad($file, 50),
            str_pad($etaStr, 8)
        );

        // New line when complete
        if ($this->current >= $this->total) {
            echo "\n";
        }
    }

    /**
     * Finaliza o progresso.
     */
    public function finish(): void
    {
        $this->current = $this->total;
        $this->render();
    }
}