#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use FlowEngine\Bootstrap\Container;
use FlowEngine\Domain\Analysis\ArchitectureValidator;

echo "🏛️ Flow Engine - Architecture Validation\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$container = new Container(__DIR__ . '/..');
$container->analyzeProject()->execute();

$flow = $container->getFlow();
$validator = new ArchitectureValidator($flow);

// Layer distribution
echo "📊 Layer Distribution:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$distribution = $validator->layerDistribution();
$total = array_sum($distribution);

foreach ($distribution as $layer => $count) {
    $percentage = $total > 0 ? round(($count / $total) * 100, 1) : 0;
    echo "  " . str_pad($layer, 15) . ": {$count} ({$percentage}%)\n";
}

// Stats
echo "\n📈 Violation Statistics:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$stats = $validator->stats();

echo "Total Violations: {$stats['totalViolations']}\n\n";

echo "By Severity:\n";
foreach ($stats['bySeverity'] as $severity => $count) {
    echo "  " . str_pad($severity, 10) . ": {$count}\n";
}

echo "\nBy Type:\n";
foreach ($stats['byType'] as $type => $count) {
    if ($count > 0) {
        echo "  {$type}: {$count}\n";
    }
}

// Violations
echo "\n🚨 Architecture Violations:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$violations = $validator->detectViolations();

if (empty($violations)) {
    echo "✅ No architecture violations detected!\n";
    echo "   Clean Architecture principles are being followed.\n\n";

    echo "Dependency Rule is respected:\n";
    echo "  ✓ Domain is isolated (no outgoing dependencies)\n";
    echo "  ✓ Application depends only on Domain\n";
    echo "  ✓ Infrastructure is properly separated\n";
} else {
    echo "Found {$stats['totalViolations']} violation(s):\n\n";

    // Group by severity
    $critical = array_filter($violations, fn($v) => $v['severity'] === 'CRITICAL');
    $high = array_filter($violations, fn($v) => $v['severity'] === 'HIGH');

    if (!empty($critical)) {
        echo "🚨 CRITICAL Violations:\n";
        foreach ($critical as $i => $v) {
            echo ($i + 1) . ". {$v['from']} → {$v['to']}\n";
            echo "   {$v['fromLayer']} → {$v['toLayer']}\n";
            echo "   Reason: {$v['reason']}\n\n";
        }
    }

    if (!empty($high)) {
        echo "⚠️  HIGH Violations:\n";
        foreach ($high as $i => $v) {
            echo ($i + 1) . ". {$v['from']} → {$v['to']}\n";
            echo "   {$v['fromLayer']} → {$v['toLayer']}\n";
            echo "   Reason: {$v['reason']}\n\n";
        }
    }
}

// Recommendations
echo "💡 Architecture Guidelines:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

if ($stats['bySeverity']['CRITICAL'] > 0) {
    echo "🚨 CRITICAL violations detected!\n";
    echo "   Domain layer MUST NOT depend on outer layers.\n";
    echo "   Fix: Use Dependency Inversion (interfaces in Domain)\n\n";
}

if ($stats['bySeverity']['HIGH'] > 0) {
    echo "⚠️  HIGH severity violations found.\n";
    echo "   Application layer should not depend on Infrastructure.\n";
    echo "   Fix: Define interfaces in Application, implement in Infrastructure\n\n";
}

if ($stats['totalViolations'] === 0) {
    echo "✅ Architecture is clean!\n\n";
}

echo "Clean Architecture Rules:\n";
echo "  1. Domain = Business logic only (no technical dependencies)\n";
echo "  2. Application = Use cases (depends only on Domain)\n";
echo "  3. Infrastructure = Technical details (depends on App + Domain)\n\n";

echo "Dependency Direction:\n";
echo "  Infrastructure → Application → Domain\n";
echo "  Infrastructure → Domain\n\n";

echo "Common Fixes:\n";
echo "  • Domain → Infrastructure: Extract interface to Domain\n";
echo "  • Application → Infrastructure: Use Dependency Inversion\n";
echo "  • Consider: Ports & Adapters pattern\n\n";

echo "✅ Analysis complete!\n";