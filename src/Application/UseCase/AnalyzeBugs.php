<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\Application\DTO\BugReportDTO;
use FlowEngine\Domain\Analysis\BugDetector;
use FlowEngine\Domain\Analysis\MetricsAnalyzer;
use FlowEngine\Domain\Contracts\BugScannerPort;
use FlowEngine\Domain\Contracts\FlowRepository;

/**
 * Orchestrates bug detection: scans files, weights findings, returns a report.
 */
final class AnalyzeBugs
{
    /** @var BugScannerPort[] */
    private array $scanners;

    public function __construct(
        private FlowRepository  $repository,
        BugScannerPort ...$scanners
    ) {
        $this->scanners = $scanners;
    }

    public function execute(int $minScore = 0, string $type = ''): BugReportDTO
    {
        $flow     = $this->repository->getFlow();
        $metrics  = new MetricsAnalyzer($flow);
        $detector = new BugDetector($flow, $metrics);

        $rawFindings = [];
        foreach ($this->scanners as $scanner) {
            $rawFindings = array_merge($rawFindings, $scanner->scan());
        }

        if ($type !== '') {
            $rawFindings = array_values(
                array_filter($rawFindings, fn(array $f) => $f['type'] === $type)
            );
        }

        $bugs  = $detector->detectBugs($rawFindings, $minScore);
        $stats = $detector->stats($bugs);

        $bugsArray = array_map(fn($b) => $b->toArray(), $bugs);

        return new BugReportDTO(
            totalBugs:    $stats['totalBugs'],
            criticalBugs: $stats['bySeverity']['CRITICAL'] ?? 0,
            bySeverity:   $stats['bySeverity'],
            byType:       $stats['byType'],
            bugs:         $bugsArray,
        );
    }
}
