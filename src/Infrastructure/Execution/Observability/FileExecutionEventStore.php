<?php

namespace FlowEngine\Infrastructure\Execution\Observability;

use FlowEngine\Domain\Execution\ExecutionContext;
use FlowEngine\Domain\Execution\ExecutionEvent;
use FlowEngine\Domain\Execution\ExecutionEventStore;
use FlowEngine\Domain\Execution\ExecutionEventType;
use RuntimeException;

/**
 * Event store persistido em arquivo JSON-Lines.
 *
 * Cada evento é gravado como uma linha JSON no arquivo.
 * Usa LOCK_EX para file locking seguro no append.
 * Zero dependências externas (sem SQLite, sem Redis).
 */
final class FileExecutionEventStore implements ExecutionEventStore
{
    public function __construct(
        private readonly string $filePath
    ) {
    }

    /**
     * Persiste um evento como uma linha JSON no arquivo.
     *
     * @param ExecutionEvent $event Evento a ser persistido
     *
     * @throws RuntimeException Se não conseguir escrever no arquivo
     */
    public function append(ExecutionEvent $event): void
    {
        $dir = dirname($this->filePath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $line = json_encode($this->serialize($event), JSON_THROW_ON_ERROR) . "\n";

        $result = file_put_contents($this->filePath, $line, FILE_APPEND | LOCK_EX);

        if ($result === false) {
            throw new RuntimeException("Failed to write event to: {$this->filePath}");
        }
    }

    /**
     * Retorna todos os eventos armazenados.
     *
     * @return ExecutionEvent[]
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
     * Retorna eventos filtrados por nodeId.
     *
     * @param string $nodeId Identificador do nó
     *
     * @return ExecutionEvent[]
     */
    public function forNode(string $nodeId): array
    {
        return array_values(
            array_filter($this->all(), fn(ExecutionEvent $e) => $e->nodeId === $nodeId)
        );
    }

    /**
     * Retorna eventos a partir de um timestamp.
     *
     * @param float $timestamp Timestamp UNIX (microtime)
     *
     * @return ExecutionEvent[]
     */
    public function since(float $timestamp): array
    {
        return array_values(
            array_filter($this->all(), fn(ExecutionEvent $e) => $e->timestamp >= $timestamp)
        );
    }

    /**
     * Serializa um ExecutionEvent para array associativo.
     *
     * @param ExecutionEvent $event
     *
     * @return array<string, mixed>
     */
    private function serialize(ExecutionEvent $event): array
    {
        return [
            'type' => $event->type->value,
            'nodeId' => $event->nodeId,
            'timestamp' => $event->timestamp,
            'durationMs' => $event->durationMs,
            'inputs' => $event->inputs,
            'output' => $event->output,
            'exception' => $event->exception !== null
                ? $event->exception->getMessage()
                : null,
        ];
    }

    /**
     * Deserializa uma linha JSON em ExecutionEvent.
     *
     * @param string $json Linha JSON
     *
     * @return ExecutionEvent
     *
     * @throws RuntimeException Se o JSON for inválido
     */
    private function deserialize(string $json): ExecutionEvent
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $type = ExecutionEventType::from($data['type']);
        $context = ExecutionContext::system('replay');

        $exception = isset($data['exception'])
            ? new RuntimeException($data['exception'])
            : null;

        return ExecutionEvent::reconstruct(
            type: $type,
            nodeId: $data['nodeId'],
            timestamp: $data['timestamp'],
            durationMs: $data['durationMs'],
            inputs: $data['inputs'] ?? [],
            output: $data['output'] ?? null,
            exception: $exception,
            context: $context
        );
    }
}
