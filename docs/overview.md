# Overview

Flow Engine is a local codebase analysis engine. It parses source files, builds a dependency graph,
and turns that graph into deterministic reports for developers and AI assistants.

The main idea is simple:

1. Point Flow Engine at a project.
2. It detects files, symbols, dependencies, entrypoints, cycles, metrics, and architecture rules.
3. You inspect the results through the CLI, MCP server, local read-only API, Docker, or exported reports.

Core outputs include:

- Nodes and edges.
- Coupling metrics and hotspots.
- Dependency cycles.
- Architecture rule findings.
- Orphan candidates.
- Bug patterns.
- Change impact and risk.
- AI-ready context.
- Local snapshots and drift checks.

Flow Engine is local-first. The core commands work without accounts, network access, telemetry, or API keys.

## Where To Go Next

- New user: [Getting started](getting-started.md).
- Command reference: [CLI commands](CLI_COMMANDS.md).
- AI client setup: [MCP server](mcp.md).
- Container workflow: [Docker](docker.md).
- Project rules: [Configuration](configuration.md).
