#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use FlowEngine\Bootstrap\Container;
use FlowEngine\Domain\Analysis\ComplexityAnalyzer;

echo "🔍 Flow Engine - Cyclomatic Complexity Analysis\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$container = new Container(__DIR__ . '/..');
$container->analyzeProject()->execute();

$flow = $container->getFlow();
$analyzer = new ComplexityAnalyzer($flow);

// Stats
echo "📊 Complexity Statistics:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$stats = $analyzer->stats();

echo "Total Methods: {$stats['total']}\n";
echo "Avg Complexity: {$stats['avgComplexity']}\n";
echo "Max Complexity: {$stats['maxComplexity']}\n";
echo "Min Complexity: {$stats['minComplexity']}\n\n";

echo "By Level:\n";
foreach ($stats['byLevel'] as $level => $count) {
    $percentage = $stats['total'] > 0 ? round(($count / $stats['total']) * 100, 1) : 0;
    echo "  " . str_pad($level, 10) . ": {$count} ({$percentage}%)\n";
}

// Complex methods
echo "\n🔥 Complex Methods (HIGH/CRITICAL):\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$complex = $analyzer->findComplexMethods();

if (empty($complex)) {
    echo "✅ No complex methods found! Code is well-structured.\n";
} else {
    echo "Found " . count($complex) . " complex method(s):\n\n";

    // Sort by complexity (descending)
    uasort($complex, fn($a, $b) => $b['complexity'] <=> $a['complexity']);

    foreach (array_slice($complex, 0, 20) as $nodeId => $data) {
        echo "• {$nodeId}\n";
        echo "  Complexity: {$data['complexity']} ({$data['level']})\n";
        echo "  Location: {$data['file']}:{$data['line']}\n\n";
    }

    if (count($complex) > 20) {
        echo "... and " . (count($complex) - 20) . " more\n\n";
    }
}

// Recommendations
echo "💡 Recommendations:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

if ($stats['byLevel']['CRITICAL'] > 0) {
    echo "⚠️  {$stats['byLevel']['CRITICAL']} CRITICAL method(s) detected!\n";
    echo "   Complexity > 50 is very hard to test and maintain.\n";
    echo "   Consider breaking these down urgently.\n\n";
}

if ($stats['byLevel']['HIGH'] > 0) {
    echo "⚠️  {$stats['byLevel']['HIGH']} HIGH complexity method(s).\n";
    echo "   Complexity 21-50 indicates methods doing too much.\n";
    echo "   Refactor when possible.\n\n";
}

if ($stats['avgComplexity'] > 10) {
    echo "⚠️  Average complexity is {$stats['avgComplexity']}.\n";
    echo "   Target: < 5 for maintainable code.\n\n";
}

if ($stats['byLevel']['CRITICAL'] === 0 && $stats['byLevel']['HIGH'] === 0) {
    echo "✅ No HIGH or CRITICAL complexity methods!\n";
    echo "   Code is maintainable and testable.\n\n";
}

echo "Complexity Guidelines (McCabe):\n";
echo "  1-10: Simple, easy to test\n";
echo "  11-20: Moderate, acceptable\n";
echo "  21-50: Complex, refactor recommended\n";
echo "  51+: Very complex, refactor urgently\n\n";

echo "✅ Analysis complete!\n";