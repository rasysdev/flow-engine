<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\Application\DTO\RemediationProposalDTO;
use FlowEngine\Application\DTO\RemediationProposalReportDTO;

/**
 * Generates actionable remediation proposals from deterministic analysis reports.
 *
 * This use case never applies changes automatically. Every proposal requires
 * explicit human approval.
 */
final class GenerateRemediationProposals
{
    public function __construct(
        private AnalyzeArchitecture $analyzeArchitecture,
        private AnalyzeMetrics $analyzeMetrics
    ) {
    }

    public function execute(int $max = 10): RemediationProposalReportDTO
    {
        $limit = max(1, $max);
        $proposals = [];

        $architecture = $this->analyzeArchitecture->execute();
        $metrics = $this->analyzeMetrics->execute();

        $architectureViolations = [];
        $seenArchitectureTargets = [];

        foreach ($architecture->violations as $violation) {
            $from = (string) ($violation['from'] ?? 'unknown');
            $to = (string) ($violation['to'] ?? 'unknown');
            $fromLayer = (string) ($violation['fromLayer'] ?? 'Unknown');
            $toLayer = (string) ($violation['toLayer'] ?? 'Unknown');

            $key = implode('|', [$from, $to, $fromLayer, $toLayer]);
            if (isset($seenArchitectureTargets[$key])) {
                continue;
            }
            $seenArchitectureTargets[$key] = true;
            $architectureViolations[] = $violation;
        }

        foreach ($architectureViolations as $index => $violation) {
            if (count($proposals) >= $limit) {
                break;
            }

            $from = (string) ($violation['from'] ?? 'unknown');
            $to = (string) ($violation['to'] ?? 'unknown');
            $severity = strtoupper((string) ($violation['severity'] ?? 'MEDIUM'));
            $priority = match ($severity) {
                'CRITICAL', 'HIGH' => 'P1',
                'MEDIUM' => 'P2',
                default => 'P3',
            };

            $reason = (string) ($violation['reason'] ?? 'Architecture dependency violation');

            $proposals[] = new RemediationProposalDTO(
                id: sprintf('arch-%03d', $index + 1),
                category: 'architecture',
                priority: $priority,
                target: "{$from} -> {$to}",
                title: 'Break forbidden dependency',
                reason: $reason,
                actions: [
                    'Introduce an application-level boundary (interface or facade).',
                    'Move infrastructure-specific logic out of the source node.',
                    'Add a regression test that asserts allowed dependency direction.',
                ],
                expectedImpact: [
                    'reduceArchitectureViolationsBy' => 1,
                    'stabilizeLayerDirection' => true,
                ],
            );
        }

        foreach ($metrics->hotspots as $index => $hotspot) {
            if (count($proposals) >= $limit) {
                break;
            }

            $nodeId = (string) ($hotspot['nodeId'] ?? 'unknown');
            $riskLevel = strtoupper((string) ($hotspot['riskLevel'] ?? 'MEDIUM'));
            $priority = match ($riskLevel) {
                'CRITICAL', 'HIGH' => 'P1',
                'MEDIUM' => 'P2',
                default => 'P3',
            };

            $complexityScore = (int) ($hotspot['complexityScore'] ?? 0);
            $blastRadius = (int) ($hotspot['blastRadius'] ?? 0);

            $proposals[] = new RemediationProposalDTO(
                id: sprintf('hotspot-%03d', $index + 1),
                category: 'hotspot',
                priority: $priority,
                target: $nodeId,
                title: 'Decompose hotspot node',
                reason: "High complexity and coupling detected (complexity={$complexityScore}, blastRadius={$blastRadius}).",
                actions: [
                    'Split responsibilities into smaller focused methods/classes.',
                    'Extract pure domain logic from side effects.',
                    'Add focused unit tests before and after refactor.',
                ],
                expectedImpact: [
                    'reduceComplexityBy' => max(1, (int) floor($complexityScore * 0.25)),
                    'reduceBlastRadiusBy' => max(1, (int) floor($blastRadius * 0.2)),
                ],
            );
        }

        usort(
            $proposals,
            static function (RemediationProposalDTO $a, RemediationProposalDTO $b): int {
                $priorityOrder = ['P1' => 1, 'P2' => 2, 'P3' => 3];
                return ($priorityOrder[$a->priority] ?? 99) <=> ($priorityOrder[$b->priority] ?? 99);
            }
        );

        $proposals = array_slice($proposals, 0, $limit);

        $byCategory = [];
        foreach ($proposals as $proposal) {
            $byCategory[$proposal->category] = ($byCategory[$proposal->category] ?? 0) + 1;
        }

        return new RemediationProposalReportDTO(
            generatedAt: (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(\DateTimeInterface::ATOM),
            total: count($proposals),
            byCategory: $byCategory,
            proposals: $proposals
        );
    }
}
