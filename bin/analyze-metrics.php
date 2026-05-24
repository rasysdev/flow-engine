#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use FlowEngine\Bootstrap\Container;
use FlowEngine\Domain\Analysis\MetricsAnalyzer;

echo "📊 Flow Engine - Metrics Analysis\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$container = new Container(__DIR__ . '/..');
$container->analyzeProject()->execute();

$flow = $container->getFlow();
$analyzer = new MetricsAnalyzer($flow);

// 1. Estatísticas gerais
echo "📈 General Statistics:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$stats = $analyzer->stats();

foreach ($stats as $key => $value) {
    $label = ucwords(str_replace('_', ' ', $key));
    echo str_pad($label, 25) . ": " . (is_float($value) ? number_format($value, 2) : $value) . "\n";
}

// 2. Hotspots (código arriscado)
echo "\n🔥 Hotspots (High Risk Code):\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$hotspots = $analyzer->hotspots();

if (empty($hotspots)) {
    echo "✅ No hotspots detected! Code is well-balanced.\n";
} else {
    echo "Found " . count($hotspots) . " hotspot(s):\n\n";

    foreach (array_slice($hotspots, 0, 10) as $i => $metrics) {
        echo ($i + 1) . ". {$metrics->nodeId}\n";
        echo "   Risk: {$metrics->riskLevel} | ";
        echo "Score: {$metrics->complexityScore()} | ";
        echo "Fan-in: {$metrics->fanIn} | ";
        echo "Fan-out: {$metrics->fanOut} | ";
        echo "Blast Radius: {$metrics->blastRadius}\n\n";
    }
}

// 3. Top métodos mais acoplados
echo "🔗 Top 10 Most Coupled Methods:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$topCoupled = $analyzer->topCoupled(10);

foreach ($topCoupled as $i => $metrics) {
    echo ($i + 1) . ". {$metrics->nodeId}\n";
    echo "   Score: " . str_pad($metrics->complexityScore(), 3) . " | ";
    echo "Fan-in: " . str_pad($metrics->fanIn, 2) . " | ";
    echo "Fan-out: " . str_pad($metrics->fanOut, 2) . "\n";
}

// 4. Recomendações
echo "\n💡 Recommendations:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

if (count($hotspots) > 0) {
    echo "⚠️  You have " . count($hotspots) . " hotspot(s).\n";
    echo "   Consider refactoring high-risk methods to reduce coupling.\n\n";
}

if ($stats['avgFanOut'] > 5) {
    echo "⚠️  Average fan-out is high (" . $stats['avgFanOut'] . ").\n";
    echo "   Methods are calling too many other methods.\n";
    echo "   Consider breaking down complex methods.\n\n";
}

if ($stats['avgFanIn'] > 5) {
    echo "⚠️  Average fan-in is high (" . $stats['avgFanIn'] . ").\n";
    echo "   Some methods are very popular (called by many).\n";
    echo "   Review if they have too many responsibilities.\n\n";
}

if (count($hotspots) === 0 && $stats['avgFanOut'] < 5 && $stats['avgFanIn'] < 5) {
    echo "✅ Code is well-structured!\n";
    echo "   Low coupling, balanced dependencies.\n\n";
}

echo "✅ Analysis complete!\n";