<?php

namespace FlowEngine\Infrastructure\Telemetry;

use FlowEngine\Domain\Analysis\AnalysisOperation;
use FlowEngine\Domain\Analysis\AnalysisSession;
use FlowEngine\Domain\Analysis\AnalysisSessionStore;
use RuntimeException;

/**
 * Persistência de sessões de análise em arquivo JSON-Lines.
 *
 * Cada sessão é gravada como uma linha JSON em `.flow-engine/sessions.jsonl`.
 * Usa LOCK_EX para file locking seguro no append.
 */
final class FileAnalysisSessionStore implements AnalysisSessionStore
{
    public function __construct(
        private readonly string $filePath
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function save(AnalysisSession $session): void
    {
        $dir = dirname($this->filePath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $line = json_encode($this->serialize($session), JSON_THROW_ON_ERROR) . "\n";

        $result = file_put_contents($this->filePath, $line, FILE_APPEND | LOCK_EX);

        if ($result === false) {
            throw new RuntimeException("Failed to write session to: {$this->filePath}");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function all(): array
    {
        if (!file_exists($this->filePath)) {
            return [];
        }

        $lines = file($this->filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return [];
        }

        return array_map(fn(string $line) => $this->deserialize($line), $lines);
    }

    /**
     * {@inheritdoc}
     */
    public function forMode(string $mode): array
    {
        return array_values(
            array_filter($this->all(), fn(AnalysisSession $s) => $s->mode === $mode)
        );
    }

    /**
     * Serializa uma sessão para array.
     *
     * @param AnalysisSession $session
     * @return array<string, mixed>
     */
    private function serialize(AnalysisSession $session): array
    {
        return [
            'id' => $session->id,
            'mode' => $session->mode,
            'goal' => $session->goal,
            'startedAt' => $session->startedAt,
            'endedAt' => $session->endedAt(),
            'operations' => array_map(fn(AnalysisOperation $op) => [
                'type' => $op->type,
                'detail' => $op->detail,
                'durationMs' => $op->durationMs,
                'timestamp' => $op->timestamp,
            ], $session->operations()),
        ];
    }

    /**
     * Deserializa uma linha JSON em AnalysisSession.
     *
     * @param string $json Linha JSON
     * @return AnalysisSession
     */
    private function deserialize(string $json): AnalysisSession
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $operations = array_map(fn(array $op) => new AnalysisOperation(
            type: $op['type'],
            detail: $op['detail'],
            durationMs: $op['durationMs'],
            timestamp: $op['timestamp']
        ), $data['operations'] ?? []);

        return AnalysisSession::reconstruct(
            id: $data['id'],
            mode: $data['mode'],
            goal: $data['goal'],
            startedAt: $data['startedAt'],
            endedAt: $data['endedAt'],
            operations: $operations
        );
    }
}
