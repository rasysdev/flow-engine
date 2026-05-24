# Concepts

## Node

A node is a code element Flow Engine can reason about, such as a method, function, class-like unit, route handler, or module-level function.

## Edge

An edge is a relationship between nodes: calls, framework entrypoints, route links, HTTP calls, imports, and other detected dependencies.

## Flow

A flow is the graph formed by nodes and edges.

## Metrics

Metrics measure graph shape: fan-in, fan-out, coupling, hotspots, and counts.

## Cycle

A cycle is a dependency loop. Flow Engine reports exact cycle members.

## Architecture Rule

Architecture rules define allowed and forbidden dependencies between layers or areas.

## Orphan Candidate

An orphan candidate is code with no detected incoming path after framework-aware suppression rules are applied.

## Context Export

Context export turns graph facts into compact Markdown for AI assistants.
