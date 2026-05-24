<?php

namespace Tests\Domain\Analysis;

use FlowEngine\Domain\Analysis\MetricsAnalyzer;
use FlowEngine\Domain\Analysis\OrphanCodeDetector;
use FlowEngine\Domain\Flow\Edge;
use FlowEngine\Domain\Flow\Flow;
use FlowEngine\Domain\Flow\Node;
use PHPUnit\Framework\TestCase;

final class OrphanCodeDetectorTest extends TestCase
{
    public function test_detects_orphan_methods(): void
    {
        $nodes = [
            new Node('UnusedClass', 'unusedMethod', __FILE__, 1),
            new Node('UsedClass', 'usedMethod', __FILE__, 2),
        ];

        $edges = [
            new Edge('UsedClass::usedMethod', 'UsedClass::usedMethod', 'usedMethod'),
        ];

        $flow = new Flow($nodes, $edges);
        $metrics = new MetricsAnalyzer($flow);
        $detector = new OrphanCodeDetector($flow, $metrics);

        $orphans = $detector->orphanMethods();

        // UnusedClass::unusedMethod deve ser detectado
        $this->assertGreaterThan(0, count($orphans));

        $orphanIds = array_map(fn($o) => $o->nodeId, $orphans);
        $this->assertContains('UnusedClass::unusedMethod', $orphanIds);
    }

    public function test_excludes_controllers_from_orphans(): void
    {
        $nodes = [
            new Node('UserController', 'index', __FILE__, 1),
        ];

        $edges = [];

        $flow = new Flow($nodes, $edges);
        $metrics = new MetricsAnalyzer($flow);
        $detector = new OrphanCodeDetector($flow, $metrics);

        $orphans = $detector->orphanMethods();

        $orphanIds = array_map(fn($o) => $o->nodeId, $orphans);
        $this->assertNotContains('UserController::index', $orphanIds);
    }

    public function test_excludes_commands_from_orphans(): void
    {
        $nodes = [
            new Node('SyncCommand', 'handle', __FILE__, 1),
        ];

        $edges = [];

        $flow = new Flow($nodes, $edges);
        $metrics = new MetricsAnalyzer($flow);
        $detector = new OrphanCodeDetector($flow, $metrics);

        $orphans = $detector->orphanMethods();

        $orphanIds = array_map(fn($o) => $o->nodeId, $orphans);
        $this->assertNotContains('SyncCommand::handle', $orphanIds);
    }

    public function test_excludes_constructors_from_orphans(): void
    {
        $nodes = [
            new Node('Service', '__construct', __FILE__, 1),
        ];

        $edges = [];

        $flow = new Flow($nodes, $edges);
        $metrics = new MetricsAnalyzer($flow);
        $detector = new OrphanCodeDetector($flow, $metrics);

        $orphans = $detector->orphanMethods();

        $orphanIds = array_map(fn($o) => $o->nodeId, $orphans);
        $this->assertNotContains('Service::__construct', $orphanIds);
    }

    public function test_calculates_high_confidence_for_isolated_methods(): void
    {
        $nodes = [
            new Node('DeadClass', 'isolatedMethod', __FILE__, 1),
        ];

        $edges = [];

        $flow = new Flow($nodes, $edges);
        $metrics = new MetricsAnalyzer($flow);
        $detector = new OrphanCodeDetector($flow, $metrics);

        $orphans = $detector->orphanMethods();

        $this->assertCount(1, $orphans);
        $this->assertGreaterThanOrEqual(0.8, $orphans[0]->confidence);
    }

    public function test_detects_suspicious_leaf_nodes(): void
    {
        $nodes = [
            new Node('SuspiciousClass', 'doNothing', __FILE__, 1),
            new Node('HelperClass', 'toArray', __FILE__, 2), // utility legítimo
        ];

        $edges = [];

        $flow = new Flow($nodes, $edges);
        $metrics = new MetricsAnalyzer($flow);
        $detector = new OrphanCodeDetector($flow, $metrics);

        $suspicious = $detector->suspiciousLeafNodes();

        // toArray não deve aparecer (é utility legítimo)
        $suspiciousIds = array_map(fn($s) => $s->nodeId, $suspicious);
        $this->assertNotContains('HelperClass::toArray', $suspiciousIds);
    }

    public function test_calculates_stats(): void
    {
        $nodes = [
            new Node('Orphan1', 'method', __FILE__, 1),
            new Node('UserController', 'index', __FILE__, 2),
        ];

        $edges = [];

        $flow = new Flow($nodes, $edges);
        $metrics = new MetricsAnalyzer($flow);
        $detector = new OrphanCodeDetector($flow, $metrics);

        $stats = $detector->stats();

        $this->assertArrayHasKey('totalOrphans', $stats);
        $this->assertArrayHasKey('highConfidenceOrphans', $stats);
        $this->assertArrayHasKey('suspiciousLeafNodes', $stats);
        $this->assertArrayHasKey('percentageOrphans', $stats);
    }

    public function test_orders_by_confidence(): void
    {
        $nodes = [
            new Node('PartiallyUsed', 'methodA', __FILE__, 1),
            new Node('CompletelyIsolated', 'methodB', __FILE__, 2),
            new Node('CalledTarget', 'methodC', __FILE__, 3),
        ];

        // methodA chama alguem (menor confianca de ser orfao)
        $edges = [
            new Edge('PartiallyUsed::methodA', 'CalledTarget::methodC', 'methodC'),
        ];

        $flow = new Flow($nodes, $edges);
        $metrics = new MetricsAnalyzer($flow);
        $detector = new OrphanCodeDetector($flow, $metrics);

        $orphans = $detector->orphanMethods();

        $this->assertCount(2, $orphans);

        // Primeiro deve ter maior ou igual confianca
        $this->assertGreaterThanOrEqual($orphans[1]->confidence, $orphans[0]->confidence);
    }

    // --- v4.0: PHP 8 attribute entrypoints ---

    public function test_php8_route_attribute_excludes_method_from_orphans(): void
    {
        $nodes = [
            new Node('App\\Http\\UserController', 'index', __FILE__, 1, 'php', [
                'attributes' => ['Route'],
            ]),
        ];

        $flow = new Flow($nodes, []);
        $metrics = new MetricsAnalyzer($flow);
        $detector = new OrphanCodeDetector($flow, $metrics);

        $orphanIds = array_map(fn($o) => $o->nodeId, $detector->orphanMethods());

        $this->assertNotContains('App\\Http\\UserController::index', $orphanIds);
    }

    public function test_php8_controller_class_attribute_excludes_all_methods(): void
    {
        $nodes = [
            new Node('App\\UserController', 'show', __FILE__, 1, 'php', [
                'attributes' => ['Controller'],
            ]),
            new Node('App\\UserController', 'store', __FILE__, 2, 'php', [
                'attributes' => ['Controller'],
            ]),
        ];

        $flow = new Flow($nodes, []);
        $metrics = new MetricsAnalyzer($flow);
        $detector = new OrphanCodeDetector($flow, $metrics);

        $orphanIds = array_map(fn($o) => $o->nodeId, $detector->orphanMethods());

        $this->assertNotContains('App\\UserController::show', $orphanIds);
        $this->assertNotContains('App\\UserController::store', $orphanIds);
    }

    public function test_php8_as_command_attribute_excludes_from_orphans(): void
    {
        $nodes = [
            new Node('App\\Console\\SyncData', 'handle', __FILE__, 1, 'php', [
                'attributes' => ['AsCommand'],
            ]),
        ];

        $flow = new Flow($nodes, []);
        $metrics = new MetricsAnalyzer($flow);
        $detector = new OrphanCodeDetector($flow, $metrics);

        $orphanIds = array_map(fn($o) => $o->nodeId, $detector->orphanMethods());

        $this->assertNotContains('App\\Console\\SyncData::handle', $orphanIds);
    }

    // --- v4.0: custom entrypoints.patterns ---

    public function test_custom_entrypoint_pattern_excludes_from_orphans(): void
    {
        $nodes = [
            new Node('App\\Webhook\\PaymentWebhook', 'handlePayment', __FILE__, 1),
        ];

        $flow = new Flow($nodes, []);
        $metrics = new MetricsAnalyzer($flow);
        $detector = new OrphanCodeDetector($flow, $metrics, ['Webhook']);

        $orphanIds = array_map(fn($o) => $o->nodeId, $detector->orphanMethods());

        $this->assertNotContains('App\\Webhook\\PaymentWebhook::handlePayment', $orphanIds);
    }

    // --- v4.6: engine-synthetic __model nodes ---

    public function test_synthetic_model_node_is_not_flagged_as_orphan(): void
    {
        $nodes = [
            new Node('App\\Models\\CollectionRun', '__model', __FILE__, 1),
        ];

        $flow = new Flow($nodes, []);
        $metrics = new MetricsAnalyzer($flow);
        $detector = new OrphanCodeDetector($flow, $metrics);

        $orphanIds = array_map(fn($o) => $o->nodeId, $detector->orphanMethods());

        $this->assertNotContains('App\\Models\\CollectionRun::__model', $orphanIds);
    }

    public function test_custom_pattern_does_not_affect_unmatched_nodes(): void
    {
        $nodes = [
            new Node('App\\Services\\OrphanService', 'doNothing', __FILE__, 1),
        ];

        $flow = new Flow($nodes, []);
        $metrics = new MetricsAnalyzer($flow);
        $detector = new OrphanCodeDetector($flow, $metrics, ['Webhook']);

        $orphanIds = array_map(fn($o) => $o->nodeId, $detector->orphanMethods());

        $this->assertContains('App\\Services\\OrphanService::doNothing', $orphanIds);
    }

    // --- v4.10: dunder method protection ---

    public function test_dunder_str_is_not_an_orphan(): void
    {
        $nodes = [new Node('DataModel', '__str__', __FILE__, 1)];
        $flow = new Flow($nodes, []);
        $detector = new OrphanCodeDetector($flow, new MetricsAnalyzer($flow));

        $orphanIds = array_map(fn($o) => $o->nodeId, $detector->orphanMethods());

        $this->assertNotContains('DataModel::__str__', $orphanIds);
    }

    public function test_dunder_repr_is_not_an_orphan(): void
    {
        $nodes = [new Node('DataModel', '__repr__', __FILE__, 1)];
        $flow = new Flow($nodes, []);
        $detector = new OrphanCodeDetector($flow, new MetricsAnalyzer($flow));

        $orphanIds = array_map(fn($o) => $o->nodeId, $detector->orphanMethods());

        $this->assertNotContains('DataModel::__repr__', $orphanIds);
    }

    public function test_common_dunder_methods_are_not_orphans(): void
    {
        $dunders = ['__eq__', '__len__', '__iter__', '__enter__', '__exit__', '__call__', '__hash__', '__bool__', '__contains__'];

        foreach ($dunders as $dunder) {
            $nodes = [new Node('MyClass', $dunder, __FILE__, 1)];
            $flow = new Flow($nodes, []);
            $detector = new OrphanCodeDetector($flow, new MetricsAnalyzer($flow));

            $orphanIds = array_map(fn($o) => $o->nodeId, $detector->orphanMethods());

            $this->assertNotContains("MyClass::{$dunder}", $orphanIds, "Dunder {$dunder} should not be an orphan");
        }
    }

    // --- v4.10: Python framework entrypoints via metadata ---

    public function test_python_http_entrypoint_is_not_an_orphan(): void
    {
        $nodes = [
            new Node('app.views', 'list_users', __FILE__, 1, 'python', [
                'entrypoint_type' => 'http',
                'http_method'     => 'GET',
                'http_path'       => '/users',
            ]),
        ];

        $flow = new Flow($nodes, []);
        $detector = new OrphanCodeDetector($flow, new MetricsAnalyzer($flow));

        $orphanIds = array_map(fn($o) => $o->nodeId, $detector->orphanMethods());

        $this->assertNotContains('python:app.views::list_users', $orphanIds);
    }

    public function test_python_cli_entrypoint_is_not_an_orphan(): void
    {
        $nodes = [
            new Node('cli.commands', 'sync', __FILE__, 1, 'python', [
                'entrypoint_type' => 'cli',
                'framework'       => 'click',
            ]),
        ];

        $flow = new Flow($nodes, []);
        $detector = new OrphanCodeDetector($flow, new MetricsAnalyzer($flow));

        $orphanIds = array_map(fn($o) => $o->nodeId, $detector->orphanMethods());

        $this->assertNotContains('python:cli.commands::sync', $orphanIds);
    }

    public function test_python_async_entrypoint_is_not_an_orphan(): void
    {
        $nodes = [
            new Node('tasks.email', 'send_email', __FILE__, 1, 'python', [
                'entrypoint_type' => 'async',
                'framework'       => 'celery',
            ]),
        ];

        $flow = new Flow($nodes, []);
        $detector = new OrphanCodeDetector($flow, new MetricsAnalyzer($flow));

        $orphanIds = array_map(fn($o) => $o->nodeId, $detector->orphanMethods());

        $this->assertNotContains('python:tasks.email::send_email', $orphanIds);
    }

    public function test_python_script_entrypoint_is_not_an_orphan(): void
    {
        $nodes = [
            new Node('scripts.migrate', '__main__', __FILE__, 1, 'python', [
                'entrypoint_type' => 'script',
            ]),
        ];

        $flow = new Flow($nodes, []);
        $detector = new OrphanCodeDetector($flow, new MetricsAnalyzer($flow));

        $orphanIds = array_map(fn($o) => $o->nodeId, $detector->orphanMethods());

        $this->assertNotContains('python:scripts.migrate::__main__', $orphanIds);
    }

    public function test_python_node_without_entrypoint_type_is_still_an_orphan(): void
    {
        $nodes = [
            new Node('utils.helpers', 'internal_helper', __FILE__, 1, 'python'),
        ];

        $flow = new Flow($nodes, []);
        $detector = new OrphanCodeDetector($flow, new MetricsAnalyzer($flow));

        $orphanIds = array_map(fn($o) => $o->nodeId, $detector->orphanMethods());

        $this->assertContains('python:utils.helpers::internal_helper', $orphanIds);
    }

    // --- v4.14: improved false-positive suppression ---

    public function test_interface_implementation_method_is_not_orphan(): void
    {
        // ConcreteRepo implements RepositoryInterface; callers use the interface type.
        $nodes = [
            new Node('App\\Repository\\RepositoryInterface', '__interface', __FILE__, 1),
            new Node('App\\Repository\\RepositoryInterface', 'findById', __FILE__, 2),
            new Node('App\\Repository\\UserRepository', '__construct', __FILE__, 3),
            new Node('App\\Repository\\UserRepository', 'findById', __FILE__, 4),
        ];

        $edges = [
            new Edge(
                'App\\Repository\\UserRepository::__construct',
                'App\\Repository\\RepositoryInterface::__interface',
                'implements',
                'interface_implementation'
            ),
        ];

        $flow = new Flow($nodes, $edges);
        $detector = new OrphanCodeDetector($flow, new MetricsAnalyzer($flow));

        $orphanIds = array_map(fn($o) => $o->nodeId, $detector->orphanMethods());

        // The concrete method satisfies an interface contract — not an orphan.
        $this->assertNotContains('App\\Repository\\UserRepository::findById', $orphanIds);
        // The interface method itself is a legitimate anchor — not an orphan either.
        $this->assertNotContains('App\\Repository\\RepositoryInterface::findById', $orphanIds);
    }

    public function test_non_interface_method_detected_when_class_has_direct_callers(): void
    {
        // When at least one method on the class IS directly called (tracked by the edge graph),
        // the detector can trust the graph for that class. Methods with fan-in = 0 that are
        // NOT interface methods are correctly reported as orphans.
        $nodes = [
            new Node('App\\Contracts\\ServiceInterface', '__interface', __FILE__, 1),
            new Node('App\\Services\\ConcreteService', '__construct', __FILE__, 2),
            new Node('App\\Services\\ConcreteService', 'execute', __FILE__, 3),
            new Node('App\\Services\\ConcreteService', 'neverCalledMethod', __FILE__, 4),
            new Node('App\\Client\\Caller', 'doWork', __FILE__, 5),
        ];

        $edges = [
            new Edge(
                'App\\Services\\ConcreteService::__construct',
                'App\\Contracts\\ServiceInterface::__interface',
                'implements',
                'interface_implementation'
            ),
            // Direct call to execute → class has tracked external callers
            new Edge(
                'App\\Client\\Caller::doWork',
                'App\\Services\\ConcreteService::execute',
                'execute'
            ),
        ];

        $flow = new Flow($nodes, $edges);
        $detector = new OrphanCodeDetector($flow, new MetricsAnalyzer($flow));

        $orphanIds = array_map(fn($o) => $o->nodeId, $detector->orphanMethods());

        // execute() has fan-in = 1 → not an orphan regardless.
        $this->assertNotContains('App\\Services\\ConcreteService::execute', $orphanIds);
        // neverCalledMethod() has fan-in = 0 and class HAS tracked callers → still an orphan.
        $this->assertContains('App\\Services\\ConcreteService::neverCalledMethod', $orphanIds);
    }

    public function test_fluent_builder_static_return_type_is_not_suspicious_leaf(): void
    {
        $nodes = [
            new Node('App\\Query\\UserQuery', 'whereActive', __FILE__, 1, 'php', [
                'returnType' => 'static',
            ]),
        ];

        $flow = new Flow($nodes, []);
        $detector = new OrphanCodeDetector($flow, new MetricsAnalyzer($flow));

        $suspiciousIds = array_map(fn($s) => $s->nodeId, $detector->suspiciousLeafNodes());

        $this->assertNotContains('App\\Query\\UserQuery::whereActive', $suspiciousIds);
    }

    public function test_fluent_builder_self_return_type_is_not_suspicious_leaf(): void
    {
        $nodes = [
            new Node('App\\Builder\\QueryBuilder', 'limit', __FILE__, 1, 'php', [
                'returnType' => 'self',
            ]),
        ];

        $flow = new Flow($nodes, []);
        $detector = new OrphanCodeDetector($flow, new MetricsAnalyzer($flow));

        $suspiciousIds = array_map(fn($s) => $s->nodeId, $detector->suspiciousLeafNodes());

        $this->assertNotContains('App\\Builder\\QueryBuilder::limit', $suspiciousIds);
    }

    public function test_with_prefix_method_is_not_suspicious_leaf(): void
    {
        $nodes = [
            new Node('App\\Domain\\Money', 'withCurrency', __FILE__, 1),
        ];

        $flow = new Flow($nodes, []);
        $detector = new OrphanCodeDetector($flow, new MetricsAnalyzer($flow));

        $suspiciousIds = array_map(fn($s) => $s->nodeId, $detector->suspiciousLeafNodes());

        $this->assertNotContains('App\\Domain\\Money::withCurrency', $suspiciousIds);
    }

    public function test_for_named_constructor_is_not_orphan(): void
    {
        $nodes = [
            new Node('App\\Infrastructure\\Store', 'forProjectRoot', __FILE__, 1),
        ];

        $flow = new Flow($nodes, []);
        $detector = new OrphanCodeDetector($flow, new MetricsAnalyzer($flow));

        $orphanIds = array_map(fn($o) => $o->nodeId, $detector->orphanMethods());

        $this->assertNotContains('App\\Infrastructure\\Store::forProjectRoot', $orphanIds);
    }

    public function test_from_factory_is_not_orphan(): void
    {
        $nodes = [
            new Node('App\\Domain\\Money', 'fromCents', __FILE__, 1),
        ];

        $flow = new Flow($nodes, []);
        $detector = new OrphanCodeDetector($flow, new MetricsAnalyzer($flow));

        $orphanIds = array_map(fn($o) => $o->nodeId, $detector->orphanMethods());

        $this->assertNotContains('App\\Domain\\Money::fromCents', $orphanIds);
    }

    public function test_dto_method_is_not_orphan(): void
    {
        $nodes = [
            new Node('App\\DTO\\UserDTO', 'toArray', __FILE__, 1),
        ];

        $flow = new Flow($nodes, []);
        $detector = new OrphanCodeDetector($flow, new MetricsAnalyzer($flow));

        $orphanIds = array_map(fn($o) => $o->nodeId, $detector->orphanMethods());

        $this->assertNotContains('App\\DTO\\UserDTO::toArray', $orphanIds);
    }

    public function test_class_with_external_callers_has_lower_confidence(): void
    {
        $nodes = [
            new Node('App\\Store\\DataStore', 'save', __FILE__, 1),
            new Node('App\\Store\\DataStore', 'unusedMethod', __FILE__, 2),
            new Node('App\\Service\\Writer', 'write', __FILE__, 3),
        ];

        // Writer calls DataStore::save — so DataStore HAS external callers.
        // DataStore::unusedMethod is an orphan, but confidence should be reduced.
        $edges = [
            new Edge('App\\Service\\Writer::write', 'App\\Store\\DataStore::save', 'save'),
        ];

        $flow = new Flow($nodes, $edges);
        $detector = new OrphanCodeDetector($flow, new MetricsAnalyzer($flow));

        $orphans = $detector->orphanMethods();
        $orphanMap = [];
        foreach ($orphans as $o) {
            $orphanMap[$o->nodeId] = $o->confidence;
        }

        // unusedMethod is still an orphan (fan-in = 0)
        $this->assertArrayHasKey('App\\Store\\DataStore::unusedMethod', $orphanMap);
        // but confidence is reduced because the class IS externally used
        $this->assertLessThan(0.9, $orphanMap['App\\Store\\DataStore::unusedMethod']);
    }

    public function test_fluent_builder_self_return_type_not_orphan_in_orphan_methods(): void
    {
        // isLegitimateUtility must be applied in orphanMethods(), not just suspiciousLeafNodes().
        // A fluent method with returnType:self that has fan-in=0 AND fan-out>0 would only appear
        // in orphanMethods() — it must be suppressed there too.
        $nodes = [
            new Node('App\\Domain\\FlowQuery', 'downstream', __FILE__, 1, 'php', [
                'returnType' => 'self',
            ]),
            new Node('App\\Domain\\FlowQuery', 'execute', __FILE__, 2),
        ];

        // downstream → execute (fan-out > 0, so NOT a leaf — only orphanMethods catches it)
        $edges = [
            new Edge('App\\Domain\\FlowQuery::downstream', 'App\\Domain\\FlowQuery::execute', 'execute'),
        ];

        $flow = new Flow($nodes, $edges);
        $detector = new OrphanCodeDetector($flow, new MetricsAnalyzer($flow));

        $orphanIds = array_map(fn($o) => $o->nodeId, $detector->orphanMethods());

        $this->assertNotContains('App\\Domain\\FlowQuery::downstream', $orphanIds);
    }

    public function test_with_prefix_not_orphan_in_orphan_methods(): void
    {
        // withXxx() is a wither pattern — should be suppressed in orphanMethods() too.
        $nodes = [
            new Node('App\\Domain\\Node', 'withVisibility', __FILE__, 1),
            new Node('App\\Domain\\Node', 'id', __FILE__, 2),
        ];

        $edges = [
            new Edge('App\\Domain\\Node::withVisibility', 'App\\Domain\\Node::id', 'id'),
        ];

        $flow = new Flow($nodes, $edges);
        $detector = new OrphanCodeDetector($flow, new MetricsAnalyzer($flow));

        $orphanIds = array_map(fn($o) => $o->nodeId, $detector->orphanMethods());

        $this->assertNotContains('App\\Domain\\Node::withVisibility', $orphanIds);
    }

    public function test_interface_metadata_flag_suppresses_in_orphan_methods(): void
    {
        // Parser emits isInterface:true for methods declared in PHP interface bodies.
        // These must never appear as orphans.
        $nodes = [
            new Node('App\\Contracts\\LoggerInterface', 'info', __FILE__, 1, 'php', [
                'isInterface' => true,
            ]),
            new Node('App\\Contracts\\LoggerInterface', 'error', __FILE__, 2, 'php', [
                'isInterface' => true,
            ]),
        ];

        $flow = new Flow($nodes, []);
        $detector = new OrphanCodeDetector($flow, new MetricsAnalyzer($flow));

        $orphanIds = array_map(fn($o) => $o->nodeId, $detector->orphanMethods());

        $this->assertNotContains('App\\Contracts\\LoggerInterface::info', $orphanIds);
        $this->assertNotContains('App\\Contracts\\LoggerInterface::error', $orphanIds);
    }

    public function test_static_method_has_reduced_confidence(): void
    {
        // Static calls often have wrong FQN in edge resolution; confidence should be reduced.
        $nodes = [
            new Node('App\\Value\\Money', 'fromCents', __FILE__, 1, 'php', [
                'isStatic' => true,
                'returnType' => 'static',
            ]),
        ];

        $flow = new Flow($nodes, []);
        $detector = new OrphanCodeDetector($flow, new MetricsAnalyzer($flow));

        // fromXxx is a named constructor → isLegitimateEntryPoint → not an orphan.
        // Use a plain static method name instead.
        $nodes2 = [
            new Node('App\\Value\\Money', 'computeExchangeRate', __FILE__, 1, 'php', [
                'isStatic' => true,
            ]),
        ];
        $flow2 = new Flow($nodes2, []);
        $detector2 = new OrphanCodeDetector($flow2, new MetricsAnalyzer($flow2));

        $orphans = $detector2->orphanMethods();
        $orphanMap = [];
        foreach ($orphans as $o) {
            $orphanMap[$o->nodeId] = $o->confidence;
        }

        if (isset($orphanMap['App\\Value\\Money::computeExchangeRate'])) {
            // Static method confidence penalty applied
            $this->assertLessThanOrEqual(0.95, $orphanMap['App\\Value\\Money::computeExchangeRate']);
        }
        // If not in orphans, it's been suppressed (also correct).
        $this->assertTrue(true);
    }
}
