# Flow Tracing (Graph Discovery)

**Version:** 1.2.0
**Status:** Stable

## Overview

Flow Tracing discovers the complete execution flow from any starting node. Given a controller method, it reveals the full chain: controller → service → repository → cache, etc.

Unlike `AnalyzeImpact` (which only returns immediate neighbors), Flow Tracing performs recursive BFS traversal with configurable depth, direction, and edge type filtering.

## Getting Started

### Via Use Case (Application Layer)

```php
$container = new Container('/path/to/project');
$traceFlow = $container->traceFlow();

// Trace everything called by Controller::store
$result = $traceFlow->execute('Controller::store');

echo "Nodes in flow: " . $result->nodes->count();
echo "Depth reached: " . $result->actualDepth;
```

### Via Fluent API (FlowQuery)

```php
$flow = $container->getFlow();

$result = $flow->query()
    ->from('Controller::store')
    ->downstream()
    ->maxDepth(5)
    ->trace();
```

## Directions

### Downstream (Default)

Discovers **who this node calls** — follows edges from the start node outward.

```php
// Controller → Service → Repository → ...
$result = $traceFlow->execute('Controller::store', 'downstream');
```

Use cases:
- Route tracing (what does this endpoint do?)
- Dependency mapping
- Understanding execution flow

### Upstream

Discovers **who calls this node** — follows edges backward to find callers.

```php
// ... → Service → Controller (who calls Repository::persist?)
$result = $traceFlow->execute('Repository::persist', 'upstream');
```

Use cases:
- Impact analysis (who depends on this?)
- Finding entry points for a deep method
- Change impact assessment

### Both

Discovers the **full connected subgraph** in both directions.

```php
// All connected nodes in both directions
$result = $traceFlow->execute('Service::save', 'both');
```

Use cases:
- Understanding a service's role in the system
- Finding complete feature boundaries
- Architectural analysis

## Depth Limiting

Control how deep the traversal goes:

```php
// Only immediate dependencies (1 level)
$result = $traceFlow->execute('Controller::store', 'downstream', maxDepth: 1);

// Deep traversal (up to 20 levels)
$result = $traceFlow->execute('Controller::store', 'downstream', maxDepth: 20);
```

Default: `maxDepth: 10`

## Edge Type Filtering

Filter which types of dependencies to follow:

```php
// Only follow method calls (ignore property access, traits, etc.)
$result = $traceFlow->execute(
    'Controller::store',
    'downstream',
    10,
    ['method_call']
);

// Follow method calls and static calls
$result = $traceFlow->execute(
    'Controller::store',
    'downstream',
    10,
    ['method_call', 'static_call']
);
```

Available edge types:
- `method_call` — `$this->method()`
- `property_access` — `$this->property`
- `static_call` — `Class::method()`
- `static_property` — `Class::$property`
- `trait_usage` — `use TraitName`
- `interface_implementation` — `implements Interface`

## Fluent API via FlowQuery

The fluent API provides an immutable, chainable interface:

```php
$flow = $container->getFlow();

// Full trace with DTO result
$traceDTO = $flow->query()
    ->from('Controller::store')
    ->downstream()
    ->maxDepth(5)
    ->onlyMethodCalls()
    ->trace();

// Quick shortcut: just get the nodes
$nodes = $flow->query()
    ->from('Controller::store')
    ->downstream()
    ->nodes();

// Upstream with custom edge types
$callers = $flow->query()
    ->from('Repository::persist')
    ->upstream()
    ->edgeTypes(['method_call', 'static_call'])
    ->trace();

// Both directions
$fullGraph = $flow->query()
    ->from('Service::save')
    ->both()
    ->maxDepth(3)
    ->trace();
```

## Result Structure (FlowTraceDTO)

```php
$result = $traceFlow->execute('Controller::store');

$result->startNodeId;  // 'Controller::store'
$result->direction;    // 'downstream'
$result->maxDepth;     // 10 (configured)
$result->actualDepth;  // 3 (actual depth reached)

$result->nodes;        // NodeCollectionDTO (iterable, countable)
$result->edges;        // EdgeDTO[] (with type and method)
$result->paths;        // string[][] (all paths from start to leaves)

// Serialization
$array = $result->toArray();
$json = $result->toJson();
```

### Paths

Paths represent all routes from the start node to each leaf:

```php
$result = $traceFlow->execute('Controller::store');

foreach ($result->paths as $path) {
    echo implode(' → ', $path) . "\n";
}
// Controller::store → Service::save → Repository::persist
// Controller::store → Service::save → Cache::put
```

## Use Cases

### 1. Route Tracing

"What happens when a user hits `POST /users`?"

```php
$result = $traceFlow->execute('UserController::store', 'downstream');

foreach ($result->nodes as $node) {
    echo "{$node->class}::{$node->method}\n";
}
```

### 2. Impact Analysis

"If I change `Repository::persist`, what breaks?"

```php
$result = $traceFlow->execute('Repository::persist', 'upstream');

echo "Affected methods: " . $result->nodes->count();
```

### 3. Feature Boundary Discovery

"What is the full scope of the 'order' feature?"

```php
$result = $traceFlow->execute('OrderService::create', 'both', maxDepth: 5);
```

### 4. Debugging

"Why is this method being called?"

```php
$result = $traceFlow->execute('SlowService::heavyComputation', 'upstream');

foreach ($result->paths as $path) {
    echo "Call chain: " . implode(' → ', $path) . "\n";
}
```

## API Reference

### TraceFlow (Use Case)

| Method | Parameters | Returns |
|---|---|---|
| `execute()` | `string $startNodeId, string $direction = 'downstream', int $maxDepth = 10, ?array $edgeTypes = null` | `FlowTraceDTO` |

### FlowQueryTrace (Fluent Builder)

| Method | Returns | Description |
|---|---|---|
| `downstream()` | `self` | Set direction to downstream |
| `upstream()` | `self` | Set direction to upstream |
| `both()` | `self` | Set direction to both |
| `maxDepth(int)` | `self` | Set max traversal depth |
| `onlyMethodCalls()` | `self` | Filter to method_call edges only |
| `edgeTypes(array)` | `self` | Filter to specific edge types |
| `trace()` | `FlowTraceDTO` | Execute and return full result |
| `nodes()` | `NodeCollectionDTO` | Execute and return only nodes |

### FlowTraceDTO

| Property | Type | Description |
|---|---|---|
| `startNodeId` | `string` | Starting node ID |
| `direction` | `string` | 'downstream', 'upstream', or 'both' |
| `maxDepth` | `int` | Configured max depth |
| `actualDepth` | `int` | Actual depth reached |
| `nodes` | `NodeCollectionDTO` | Discovered nodes |
| `edges` | `EdgeDTO[]` | Traversed edges |
| `paths` | `string[][]` | All paths from start to leaves |

### EdgeDTO (Updated)

| Property | Type | Description |
|---|---|---|
| `from` | `string` | Source node ID |
| `to` | `string` | Target node ID |
| `type` | `string` | Edge type (method_call, property_access, etc.) |
| `method` | `string` | Method/property name |
