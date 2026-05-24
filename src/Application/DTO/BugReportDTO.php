<?php

namespace FlowEngine\Application\DTO;

use FlowEngine\Domain\Analysis\Bug;

/**
 * Report of all detected potential bugs, weighted by graph impact.
 *
 * @api
 */
final readonly class BugReportDTO
{
    /**
     * @param int                      $totalBugs    Total number of detected bugs
     * @param int                      $criticalBugs Number of CRITICAL-severity bugs
     * @param array<string, int>       $bySeverity   Bug counts keyed by severity label
     * @param array<string, int>       $byType       Bug counts keyed by bug type
     * @param array<int, array<string, mixed>> $bugs Bug entries sorted by bugScore desc
     */
    public function __construct(
        public int   $totalBugs,
        public int   $criticalBugs,
        public array $bySeverity,
        public array $byType,
        public array $bugs
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'totalBugs'    => $this->totalBugs,
            'criticalBugs' => $this->criticalBugs,
            'bySeverity'   => $this->bySeverity,
            'byType'       => $this->byType,
            'bugs'         => $this->bugs,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }
}
