#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use FlowEngine\Bootstrap\Container;
use FlowEngine\Domain\Analysis\MetricsAnalyzer;
use FlowEngine\Domain\Analysis\OrphanCodeDetector;

echo "💀 Flow Engine - Orphan Code Detection\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$container = new Container(__DIR__ . '/..');
$container->analyzeProject()->execute();

$flow = $container->getFlow();
$metrics = new MetricsAnalyzer($flow);
$detector = new OrphanCodeDetector($flow, $metrics);

// 1. Estatísticas
echo "📊 Statistics:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$stats = $detector->stats();

echo "Total Orphan Methods: {$stats['totalOrphans']}\n";
echo "High Confidence: {$stats['highConfidenceOrphans']}\n";
echo "Suspicious Leaf Nodes: {$stats['suspiciousLeafNodes']}\n";
echo "Percentage of Codebase: {$stats['percentageOrphans']}%\n";

// 2. Órfãos de alta confiança
echo "\n🔴 HIGH/VERY HIGH Confidence Orphans:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$orphans = $detector->orphanMethods();
$highConfidence = array_filter(
    $orphans,
    fn($o) => in_array($o->confidenceLevel(), ['HIGH', 'VERY_HIGH'])
);

if (empty($highConfidence)) {
    echo "✅ No high-confidence orphans detected!\n";
} else {
    foreach (array_slice($highConfidence, 0, 15) as $i => $orphan) {
        echo ($i + 1) . ". {$orphan->nodeId}\n";
        echo "   Confidence: {$orphan->confidencePercentage()}% ({$orphan->confidenceLevel()})\n";
        echo "   Reason: {$orphan->reason}\n";
        echo "   Safe to remove: " . ($orphan->isSafeToRemove() ? 'YES' : 'REVIEW') . "\n\n";
    }

    if (count($highConfidence) > 15) {
        echo "... and " . (count($highConfidence) - 15) . " more\n\n";
    }
}

// 3. Órfãos de média confiança
echo "\n🟡 MEDIUM Confidence Orphans:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$mediumConfidence = array_filter(
    $orphans,
    fn($o) => $o->confidenceLevel() === 'MEDIUM'
);

if (empty($mediumConfidence)) {
    echo "✅ No medium-confidence orphans detected!\n";
} else {
    echo "Found " . count($mediumConfidence) . " medium-confidence orphan(s).\n";
    echo "Review these manually before considering removal.\n\n";

    foreach (array_slice($mediumConfidence, 0, 10) as $i => $orphan) {
        echo ($i + 1) . ". {$orphan->nodeId} ({$orphan->confidencePercentage()}%)\n";
    }

    if (count($mediumConfidence) > 10) {
        echo "... and " . (count($mediumConfidence) - 10) . " more\n";
    }
}

// 4. Leaf nodes suspeitos
echo "\n\n🍃 Suspicious Leaf Nodes:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$suspicious = $detector->suspiciousLeafNodes();

if (empty($suspicious)) {
    echo "✅ No suspicious leaf nodes detected!\n";
} else {
    echo "Found " . count($suspicious) . " suspicious leaf node(s).\n";
    echo "These methods don't call anyone and aren't recognized utilities.\n\n";

    foreach (array_slice($suspicious, 0, 10) as $i => $node) {
        echo ($i + 1) . ". {$node->nodeId} ({$node->confidencePercentage()}%)\n";
    }

    if (count($suspicious) > 10) {
        echo "... and " . (count($suspicious) - 10) . " more\n";
    }
}

// 5. Recomendações
echo "\n\n💡 Recommendations:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

if ($stats['highConfidenceOrphans'] > 0) {
    echo "⚠️  {$stats['highConfidenceOrphans']} high-confidence orphan(s) detected.\n";
    echo "   These are likely safe to remove after manual review.\n\n";
}

if ($stats['percentageOrphans'] > 20) {
    echo "⚠️  {$stats['percentageOrphans']}% of codebase appears unused.\n";
    echo "   This might indicate:\n";
    echo "   • Legitimate dead code\n";
    echo "   • Code called via reflection/DI\n";
    echo "   • Incomplete edge detection\n\n";
}

if ($stats['totalOrphans'] === 0) {
    echo "✅ No orphan code detected!\n";
    echo "   All methods are used or recognized as entry points.\n\n";
}

echo "⚠️  IMPORTANT DISCLAIMER:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Always review orphans manually before deleting!\n\n";
echo "Some methods might be used via:\n";
echo "• Dependency injection containers\n";
echo "• Reflection/magic methods (__call, __get)\n";
echo "• Framework hooks and lifecycle methods\n";
echo "• External callers (APIs, webhooks, event listeners)\n";
echo "• Configuration-based routing\n\n";

echo "Use this analysis as a STARTING POINT for code cleanup,\n";
echo "not as definitive proof that code should be deleted.\n\n";

echo "✅ Analysis complete!\n";