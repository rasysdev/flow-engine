<?php

namespace FlowEngine\Domain\Analysis;

/**
 * Pure risk calculation service.
 *
 * Formula: (blastRadius * 3) + (fanIn * 2) + (fanOut * 1.5)
 *        + (cycleCount * 10) + (violationCount * 8) + (cyclomaticComplexity * 0.5)
 *
 * Normalized to 0-100. Each factor tracked for transparency.
 *
 * Risk levels: 0-25 LOW, 26-50 MEDIUM, 51-75 HIGH, 76-100 CRITICAL.
 */
final class RiskScorer
{
    private const WEIGHTS = [
        'blastRadius' => 3.0,
        'fanIn' => 2.0,
        'fanOut' => 1.5,
        'cycleCount' => 10.0,
        'violationCount' => 8.0,
        'cyclomaticComplexity' => 0.5,
    ];

    private const MAX_RAW_SCORE = 200.0;

    public function score(
        NodeMetrics $metrics,
        int $cycleCount,
        int $violationCount,
        int $cyclomaticComplexity
    ): RiskScore {
        $values = [
            'blastRadius' => $metrics->blastRadius,
            'fanIn' => $metrics->fanIn,
            'fanOut' => $metrics->fanOut,
            'cycleCount' => $cycleCount,
            'violationCount' => $violationCount,
            'cyclomaticComplexity' => $cyclomaticComplexity,
        ];

        $factors = [];
        $rawScore = 0.0;

        foreach (self::WEIGHTS as $name => $weight) {
            $value = (float) $values[$name];
            $contribution = $value * $weight;
            $rawScore += $contribution;

            $factors[] = [
                'name' => $name,
                'weight' => $weight,
                'value' => $value,
                'contribution' => round($contribution, 2),
            ];
        }

        $normalized = (int) min(100, round(($rawScore / self::MAX_RAW_SCORE) * 100));
        $level = self::levelFor($normalized);

        return new RiskScore(
            score: $normalized,
            level: $level,
            factors: $factors
        );
    }

    private static function levelFor(int $score): string
    {
        if ($score <= 25) {
            return 'LOW';
        }

        if ($score <= 50) {
            return 'MEDIUM';
        }

        if ($score <= 75) {
            return 'HIGH';
        }

        return 'CRITICAL';
    }
}
