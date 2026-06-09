# Concepts

## Node

A node is a code element Flow Engine can reason about, such as a method, function, class-like unit, route handler, or module-level function.

Example: `App\\Service\\OrderService::process`.

## Edge

An edge is a relationship between nodes: calls, framework entrypoints, route links, HTTP calls, imports, and other detected dependencies.

## Flow

A flow is the graph formed by nodes and edges.

Use `flow <path>` or `nodes <path>` to inspect it from the CLI.

## Metrics

Metrics measure graph shape: fan-in, fan-out, coupling, hotspots, and counts.

## Cycle

A cycle is a dependency loop. Flow Engine reports exact cycle members.

## Architecture Rule

Architecture rules define allowed and forbidden dependencies between layers or areas.

Example: domain code should not depend on infrastructure code.

## Orphan Candidate

An orphan candidate is code with no detected incoming path after framework-aware suppression rules are applied.

## Context Export

Context export turns graph facts into compact Markdown for AI assistants.

Use `context <path> --minimal` for a small export, or `context <path> --entrypoint=<node>` for focused context around one code path.
