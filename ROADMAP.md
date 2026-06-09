# Roadmap

## Current Focus

Flow Engine is an open-source, local-first dependency graph and architecture analysis engine.
The public package focuses on deterministic code facts that developers and AI assistants can inspect
without sending source code to a remote service.

## Public Core

- Multi-language parsing and dependency graph construction
- Metrics, coupling, cycles, architecture checks, orphan detection, impact, and risk
- Local CLI workflows
- Local read-only API
- MCP server
- Docker usage
- Snapshot, compare, drift, cleanup, and architecture-gate commands backed by local files
- Context and report exports for AI assistants

## Near-Term Work

- Improve parser coverage and cross-language edge detection
- Expand framework-aware entrypoint and orphan suppression rules
- Tighten architecture policy configuration
- Improve compact context exports for AI workflows
- Add more examples for CI and local development
- Keep docs aligned with the public API and CLI

## Longer-Term Public Work

- More deterministic reports
- Better large-repository performance
- More language fixtures and regression tests
- Additional local export formats
- Stronger MCP discovery and lookup workflows

## Not In The Public Core

Some advanced product workflows may live outside this repository later. The public core should stay
useful on its own: local, scriptable, inspectable, and safe to run in private codebases.
