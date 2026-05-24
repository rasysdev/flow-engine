#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use FlowEngine\Bootstrap\Container;
use FlowEngine\Domain\Analysis\CycleDetector;

echo "🔄 Flow Engine - Dependency Cycles Detection\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$container = new Container(__DIR__ . '/..');
$container->analyzeProject()->execute();

$flow = $container->getFlow();
$detector = new CycleDetector($flow);

// Stats
echo "📊 Cycle Statistics:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$stats = $detector->stats();

echo "Total Cycles: {$stats['totalCycles']}\n";
echo "Nodes in Cycles: {$stats['totalNodesInCycles']}\n";
echo "Largest Cycle: {$stats['largestCycle']} nodes\n\n";

echo "By Severity:\n";
foreach ($stats['bySeverity'] as $severity => $count) {
    echo "  " . str_pad($severity, 10) . ": {$count}\n";
}

// Detailed cycles
echo "\n🔄 Detected Cycles:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$cycles = $detector->detectCycles();

if (empty($cycles)) {
    echo "✅ No circular dependencies detected!\n";
    echo "   Codebase has a clean dependency graph.\n";
} else {
    echo "Found {$stats['totalCycles']} cycle(s):\n\n";

    foreach ($cycles as $i => $cycle) {
        echo ($i + 1) . ". Cycle of {$cycle['size']} nodes ({$cycle['severity']})\n";

        // Show cycle path
        echo "   Path: ";
        $path = implode(' → ', array_slice($cycle['nodes'], 0, min(5, count($cycle['nodes']))));
        if (count($cycle['nodes']) > 5) {
            $path .= ' → ... → ' . $cycle['nodes'][0];
        } else {
            $path .= ' → ' . $cycle['nodes'][0];
        }
        echo $path . "\n";

        // List all nodes
        echo "   Nodes:\n";
        foreach ($cycle['nodes'] as $node) {
            echo "     • {$node}\n";
        }
        echo "\n";
    }
}

// Recommendations
echo "💡 Recommendations:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

if ($stats['bySeverity']['CRITICAL'] > 0) {
    echo "🚨 {$stats['bySeverity']['CRITICAL']} CRITICAL cycle(s) detected!\n";
    echo "   Large cycles (11+ nodes) indicate severe architectural issues.\n";
    echo "   URGENT: Break these cycles immediately.\n\n";
}

if ($stats['bySeverity']['HIGH'] > 0) {
    echo "⚠️  {$stats['bySeverity']['HIGH']} HIGH severity cycle(s).\n";
    echo "   Cycles of 6-10 nodes are difficult to untangle.\n";
    echo "   Priority: Break these cycles soon.\n\n";
}

if ($stats['bySeverity']['MEDIUM'] > 0) {
    echo "⚠️  {$stats['bySeverity']['MEDIUM']} MEDIUM severity cycle(s).\n";
    echo "   Cycles of 3-5 nodes should be addressed.\n";
    echo "   Consider: Extract interface or introduce mediator.\n\n";
}

if ($stats['bySeverity']['LOW'] > 0) {
    echo "ℹ️  {$stats['bySeverity']['LOW']} LOW severity cycle(s).\n";
    echo "   Simple 2-node cycles are easiest to fix.\n";
    echo "   Consider: Merge classes or extract shared logic.\n\n";
}

if ($stats['totalCycles'] === 0) {
    echo "✅ No circular dependencies!\n";
    echo "   Clean architecture with proper dependency direction.\n";
    echo "   Code is modular and testable.\n\n";
}

echo "Why cycles are bad:\n";
echo "  • Impossible to test components in isolation\n";
echo "  • Prevent modularization and extraction\n";
echo "  • Cause subtle bugs (loading order issues)\n";
echo "  • Make refactoring very difficult\n\n";

echo "How to break cycles:\n";
echo "  1. Extract interface (Dependency Inversion)\n";
echo "  2. Introduce mediator/event bus\n";
echo "  3. Merge classes if they're too coupled\n";
echo "  4. Extract shared logic to a third class\n\n";

echo "✅ Analysis complete!\n";