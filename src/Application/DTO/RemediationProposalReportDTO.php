<?php

namespace FlowEngine\Application\DTO;

/**
 * Remediation proposal report generated from deterministic analysis outputs.
 *
 * @api
 */
final readonly class RemediationProposalReportDTO
{
    /**
     * @param string $generatedAt ISO timestamp
     * @param int $total Total proposals
     * @param array<string, int> $byCategory Totals grouped by category
     * @param RemediationProposalDTO[] $proposals Ordered proposals
     */
    public function __construct(
        public string $generatedAt,
        public int $total,
        public array $byCategory,
        public array $proposals
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'generatedAt' => $this->generatedAt,
            'total' => $this->total,
            'byCategory' => $this->byCategory,
            'proposals' => array_map(
                static fn(RemediationProposalDTO $proposal) => $proposal->toArray(),
                $this->proposals
            ),
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }
}

