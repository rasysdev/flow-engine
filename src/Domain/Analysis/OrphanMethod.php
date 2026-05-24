<?php

namespace FlowEngine\Domain\Analysis;

/**
 * Representa um método potencialmente órfão.
 */
final readonly class OrphanMethod
{
    public function __construct(
        public string $nodeId,
        public string $reason,
        public float $confidence
    ) {
    }

    /**
     * Retorna nível de confiança em formato texto.
     */
    public function confidenceLevel(): string
    {
        if ($this->confidence >= 0.8) {
            return 'VERY_HIGH';
        }

        if ($this->confidence >= 0.6) {
            return 'HIGH';
        }

        if ($this->confidence >= 0.4) {
            return 'MEDIUM';
        }

        return 'LOW';
    }

    /**
     * Confiança como porcentagem.
     */
    public function confidencePercentage(): int
    {
        return (int) round($this->confidence * 100);
    }

    /**
     * Verifica se é seguro remover.
     */
    public function isSafeToRemove(): bool
    {
        return $this->confidence >= 0.8;
    }

    /**
     * Serializa para array.
     */
    public function toArray(): array
    {
        return [
            'nodeId' => $this->nodeId,
            'reason' => $this->reason,
            'confidence' => $this->confidence,
            'confidencePercentage' => $this->confidencePercentage(),
            'confidenceLevel' => $this->confidenceLevel(),
            'safeToRemove' => $this->isSafeToRemove(),
        ];
    }
}
