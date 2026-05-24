#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use FlowEngine\Bootstrap\Container;

echo "🔍 Flow Engine analyzing itself (v2.0 - with edges)...\n\n";

$container = new Container(__DIR__ . '/..');
$container->analyzeProject()->execute();

$flow = $container->getFlow();

echo "📊 Flow Engine Stats:\n";
echo "━━━━━━━━━━━━━━━━━━━━\n";
echo "Total Methods: " . $flow->nodeCount() . "\n";
echo "Total Calls: " . $flow->edgeCount() . "\n";
echo "Public Methods: " . $flow->query()->publicNodes()->count() . "\n";

// Entry points e leaf nodes agora fazem sentido!
if ($flow->edgeCount() > 0) {
    echo "Entry Points: " . $flow->query()->entrypoints()->count() . "\n";
    echo "Leaf Nodes: " . $flow->query()->leafNodes()->count() . "\n";
} else {
    echo "Entry Points: N/A (no edges detected)\n";
    echo "Leaf Nodes: N/A (no edges detected)\n";
}

echo "\n🏗️ By Layer:\n";
echo "━━━━━━━━━━━━━━━━━━━━\n";

$layers = [
    'Domain' => 'FlowEngine\Domain',
    'Application' => 'FlowEngine\Application',
    'Infrastructure' => 'FlowEngine\Infrastructure',
    'AI' => 'FlowEngine\AI',
    'Bootstrap' => 'FlowEngine\Bootstrap',
    'Execution' => 'FlowEngine\Execution',
];

foreach ($layers as $name => $namespace) {
    $count = $flow->query()->byNamespace($namespace)->count();
    echo str_pad($name, 15) . ": {$count}\n";
}

echo "\n📦 Top Namespaces:\n";
echo "━━━━━━━━━━━━━━━━━━━━\n";

$allNodes = $flow->query()->all();
$namespaces = [];

foreach ($allNodes as $node) {
    $parts = explode('\\', $node->class);
    if (count($parts) >= 3) {
        $topLevel = $parts[0] . '\\' . $parts[1] . '\\' . $parts[2];
        $namespaces[$topLevel] = ($namespaces[$topLevel] ?? 0) + 1;
    }
}

arsort($namespaces);

foreach (array_slice($namespaces, 0, 10) as $ns => $count) {
    echo str_pad($ns, 50) . ": {$count}\n";
}

// Novos insights com edges!
if ($flow->edgeCount() > 0) {
    echo "\n🎯 Entry Points (methods with no callers):\n";
    echo "━━━━━━━━━━━━━━━━━━━━\n";

    $entryPoints = $flow->query()
        ->entrypoints()
        ->excludeVendor()
        ->all();

    foreach (array_slice($entryPoints, 0, 10) as $entry) {
        echo "• {$entry->id}\n";
    }

    echo "\n🍃 Leaf Nodes (methods that call nobody):\n";
    echo "━━━━━━━━━━━━━━━━━━━━\n";

    $leaves = $flow->query()
        ->leafNodes()
        ->excludeVendor()
        ->all();

    foreach (array_slice($leaves, 0, 10) as $leaf) {
        echo "• {$leaf->id}\n";
    }

    echo "\n🔥 Sample Dependencies:\n";
    echo "━━━━━━━━━━━━━━━━━━━━\n";

    foreach (array_slice($flow->edges(), 0, 10) as $edge) {
        echo "{$edge->from()}\n";
        echo "  └─> calls {$edge->method()}() on {$edge->to()}\n";
    }
}

echo "\n✅ Self-analysis complete!\n";

if ($flow->edgeCount() === 0) {
    echo "\n⚠️ No edges detected. This might indicate:\n";
    echo "   - Edge detection not fully implemented\n";
    echo "   - Project uses advanced patterns (DI, etc)\n";
}
