<?php

namespace FlowEngine\Domain\Output;

use FlowEngine\Domain\Analysis\OrphanCodeDetector;
use FlowEngine\Domain\Contracts\Flow;

/**
 * Gera relatório de texto de código órfão.
 * 
 * Formato legível e pronto para revisão manual.
 */
final class OrphanCodeTxtGenerator
{
    public function __construct(
        private Flow $flow,
        private OrphanCodeDetector $orphanDetector
    ) {
    }

    /**
     * Gera relatório completo em texto.
     * 
     * @api
     */
    public function generate(): string
    {
        $sections = [];

        $sections[] = $this->generateHeader();
        $sections[] = $this->generateStats();
        $sections[] = $this->generateHighConfidence();
        $sections[] = $this->generateMediumConfidence();
        $sections[] = $this->generateSuspiciousLeafNodes();
        $sections[] = $this->generateRecommendations();
        $sections[] = $this->generateDisclaimer();

        return implode("\n\n", $sections);
    }

    /**
     * Cabeçalho do relatório.
     */
    private function generateHeader(): string
    {
        $timestamp = date('Y-m-d H:i:s');

        return <<<TEXT
╔═══════════════════════════════════════════════════════════════╗
║           FLOW ENGINE - ORPHAN CODE REPORT                    ║
╚═══════════════════════════════════════════════════════════════╝

Generated: {$timestamp}
TEXT;
    }

    /**
     * Estatísticas gerais.
     */
    private function generateStats(): string
    {
        $stats = $this->orphanDetector->stats();

        return <<<TEXT
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📊 STATISTICS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Total Orphan Methods: {$stats['totalOrphans']}
High Confidence: {$stats['highConfidenceOrphans']}
Suspicious Leaf Nodes: {$stats['suspiciousLeafNodes']}
Percentage of Codebase: {$stats['percentageOrphans']}%
TEXT;
    }

    /**
     * Órfãos de alta confiança.
     */
    private function generateHighConfidence(): string
    {
        $orphans = $this->orphanDetector->orphanMethods();
        $highConfidence = array_filter(
            $orphans,
            fn($o) => in_array($o->confidenceLevel(), ['HIGH', 'VERY_HIGH'])
        );

        $text = <<<TEXT
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🔴 HIGH/VERY HIGH CONFIDENCE ORPHANS (Safe to Remove)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
TEXT;

        if (empty($highConfidence)) {
            $text .= "\n\n✅ No high-confidence orphans detected!";
            return $text;
        }

        $text .= "\n\nFound " . count($highConfidence) . " high-confidence orphan(s):\n";

        foreach (array_slice($highConfidence, 0, 50) as $i => $orphan) {
            $num = $i + 1;
            $safeToRemove = $orphan->isSafeToRemove() ? 'YES' : 'REVIEW';

            $text .= <<<ORPHAN

{$num}. {$orphan->nodeId}
   Confidence: {$orphan->confidencePercentage()}% ({$orphan->confidenceLevel()})
   Reason: {$orphan->reason}
   Safe to remove: {$safeToRemove}
ORPHAN;
        }

        if (count($highConfidence) > 50) {
            $remaining = count($highConfidence) - 50;
            $text .= "\n\n... and {$remaining} more\n";
            $text .= "(See full list in JSON report)";
        }

        return $text;
    }

    /**
     * Órfãos de média confiança.
     */
    private function generateMediumConfidence(): string
    {
        $orphans = $this->orphanDetector->orphanMethods();
        $mediumConfidence = array_filter(
            $orphans,
            fn($o) => $o->confidenceLevel() === 'MEDIUM'
        );

        $text = <<<TEXT
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🟡 MEDIUM CONFIDENCE ORPHANS (Review Before Removing)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
TEXT;

        if (empty($mediumConfidence)) {
            $text .= "\n\n✅ No medium-confidence orphans detected!";
            return $text;
        }

        $text .= "\n\nFound " . count($mediumConfidence) . " medium-confidence orphan(s).\n";
        $text .= "Review these manually before considering removal.\n";

        foreach (array_slice($mediumConfidence, 0, 20) as $i => $orphan) {
            $num = $i + 1;
            $text .= "\n{$num}. {$orphan->nodeId} ({$orphan->confidencePercentage()}%)";
        }

        if (count($mediumConfidence) > 20) {
            $remaining = count($mediumConfidence) - 20;
            $text .= "\n\n... and {$remaining} more";
        }

        return $text;
    }

    /**
     * Leaf nodes suspeitos.
     */
    private function generateSuspiciousLeafNodes(): string
    {
        $suspicious = $this->orphanDetector->suspiciousLeafNodes();

        $text = <<<TEXT
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🍃 SUSPICIOUS LEAF NODES
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
TEXT;

        if (empty($suspicious)) {
            $text .= "\n\n✅ No suspicious leaf nodes detected!";
            return $text;
        }

        $text .= "\n\nFound " . count($suspicious) . " suspicious leaf node(s).\n";
        $text .= "These methods don't call anyone and aren't recognized utilities.\n";

        foreach (array_slice($suspicious, 0, 20) as $i => $node) {
            $num = $i + 1;
            $text .= "\n{$num}. {$node->nodeId} ({$node->confidencePercentage()}%)";
        }

        if (count($suspicious) > 20) {
            $remaining = count($suspicious) - 20;
            $text .= "\n\n... and {$remaining} more";
        }

        return $text;
    }

    /**
     * Recomendações.
     */
    private function generateRecommendations(): string
    {
        $stats = $this->orphanDetector->stats();

        $recommendations = [];

        if ($stats['highConfidenceOrphans'] > 0) {
            $recommendations[] = "⚠️  {$stats['highConfidenceOrphans']} high-confidence orphan(s) detected.";
            $recommendations[] = "   These are likely safe to remove after manual review.";
        }

        if ($stats['percentageOrphans'] > 20) {
            $recommendations[] = "\n⚠️  {$stats['percentageOrphans']}% of codebase appears unused.";
            $recommendations[] = "   This might indicate:";
            $recommendations[] = "   • Legitimate dead code that can be removed";
            $recommendations[] = "   • Code called via reflection/DI";
            $recommendations[] = "   • Incomplete edge detection (dynamic calls)";
        }

        if ($stats['totalOrphans'] === 0) {
            $recommendations[] = "✅ No orphan code detected!";
            $recommendations[] = "   All methods are used or recognized as entry points.";
        }

        if (empty($recommendations)) {
            $recommendations[] = "✅ Low percentage of orphan code. Codebase is healthy.";
        }

        return <<<TEXT
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
💡 RECOMMENDATIONS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

TEXT . implode("\n", $recommendations);
    }

    /**
     * Disclaimer.
     */
    private function generateDisclaimer(): string
    {
        return <<<TEXT
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
⚠️  IMPORTANT DISCLAIMER
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Always review orphans manually before deleting code!

Some methods might be used via:
• Dependency injection containers
• Reflection/magic methods (__call, __get, __set)
• Framework hooks and lifecycle methods
• External callers (APIs, webhooks, event listeners)
• Configuration-based routing
• Template engines (Blade, Twig)

Use this analysis as a STARTING POINT for code cleanup,
not as definitive proof that code should be deleted.

Best practices:
1. Start with 100% confidence orphans
2. Review each method's context
3. Search codebase for dynamic calls
4. Check framework documentation
5. Run full test suite after deletion
6. Deploy to staging first

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Generated by Flow Engine • https://github.com/rborges/flow-engine
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
TEXT;
    }
}