<p align="center">
  <img src="docs/assets/flow-engine-logo-text.svg" alt="Flow Engine" width="620">
</p>

<p align="center">
  <strong>Local dependency graph and factual context for AI-assisted software work.</strong>
</p>

<p align="center">
  <a href="docs/README.md">Documentation</a> |
  <a href="docs/CLI_COMMANDS.md">CLI</a> |
  <a href="docs/mcp.md">MCP</a> |
  <a href="docs/docker.md">Docker</a> |
  <a href="docs/configuration.md">Configuration</a> |
  <a href="public-api.md">Public API</a> |
  <a href="ROADMAP.md">Roadmap</a>
</p>

<p align="center">
  <strong>MIT | PHP 8.3+ | MCP server | Docker | Local first</strong>
</p>

Detailed documentation lives in [docs/](docs/README.md).

<p align="center">
  <img src="docs/assets/flow-engine-graph.svg" alt="Flow Engine dependency graph" width="860">
</p>

**Flow Engine** analyzes a codebase on your machine and turns it into graph facts an AI assistant can trust: files, symbols, dependencies, cycles, impact, risk, architecture rules, orphan candidates, bug patterns, snapshots, and compact context exports.

The core is local-first. CLI analysis, MCP tools, Docker usage, the read-only API, reports, and context exports work without telemetry, accounts, or required API keys.

## Why Flow Engine

AI coding tools work better when they start from deterministic codebase facts instead of broad file dumps or guesses.

Use Flow Engine to:

- Give an AI assistant grounded context before code changes.
- Find cycles, hotspots, orphan candidates, and architecture violations.
- Estimate impact and risk for a class, method, route, command, or module.
- Enforce local architecture rules in CI.
- Expose local graph tools through MCP.

## What It Includes

| Area | Included |
| --- | --- |
| Engine | Static analysis, graph construction, metrics, cycles, impact, risk, architecture checks, orphan detection |
| Languages | PHP, TypeScript, JavaScript, Python, Go, Dart, and Blade |
| Interfaces | CLI, MCP server, local read-only HTTP API, Docker |
| AI context | Minimal/full context exports, entrypoint-focused context, reports, Mermaid output |
| Change tracking | Snapshots, compare, drift, cleanup, architecture gate |
| Privacy | No required remote service, API key, telemetry, or account system |

## Install From Source

Windows PowerShell:

```powershell
git clone https://github.com/rborges/flow-engine.git
cd flow-engine
composer install
php .\bin\engine.php help
```

Linux/macOS:

```bash
git clone https://github.com/rborges/flow-engine.git
cd flow-engine
composer install
php bin/engine.php help
```

Composer package install will be documented once the package is published.

## Quick Start

Run commands from the Flow Engine directory and replace `<project>` with the project you want to analyze.

```bash
php bin/engine.php analyze <project>
php bin/engine.php metrics <project>
php bin/engine.php cycles <project>
php bin/engine.php architecture <project>
php bin/engine.php orphans <project> --audit
php bin/engine.php context <project> --minimal
```

On Windows PowerShell, use `php .\bin\engine.php`.

If you are already inside the project you want to analyze, use `.` as the project path.

## Docker Quick Start

```bash
docker build -t flow-engine .
docker run --rm -v "$PWD:/workspace:ro" flow-engine analyze /workspace
docker run --rm -v "$PWD:/workspace:ro" flow-engine context /workspace --minimal
```

On Windows PowerShell, use a Windows mount path:

```powershell
docker run --rm -v "C:\dev\my-app:/workspace:ro" flow-engine analyze /workspace
```

See [docs/docker.md](docs/docker.md) for Docker Compose and local API examples.

## MCP Server

Flow Engine can run as a local MCP server so compatible AI clients can inspect the codebase through tools instead of pasted reports.

```bash
export FLOW_ENGINE_BIN="$PWD/bin/engine.php"
php bin/engine.php mcp
```

The committed `.mcp.json` uses `FLOW_ENGINE_BIN`, so it does not contain machine-specific paths.

See [docs/mcp.md](docs/mcp.md) for setup, available tools, and the recommended AI workflow.

## Documentation Map

- [Getting started](docs/getting-started.md): first run on Windows, Linux/macOS, or Docker.
- [Overview](docs/overview.md): what Flow Engine does and where each interface fits.
- [CLI commands](docs/CLI_COMMANDS.md): command reference with common examples.
- [MCP server](docs/mcp.md): connect Flow Engine to MCP-compatible AI clients.
- [Docker](docs/docker.md): run analysis and the local API in containers.
- [Configuration](docs/configuration.md): `flow-engine.json` fields and examples.
- [Analysis tools](docs/analysis-tools.md): metrics, cycles, architecture, orphans, impact, risk, and reports.
- [Flow tracing](docs/flow-tracing.md): inspect dependencies around one node.
- [System diagrams](docs/system-diagrams.md): generate Mermaid diagrams and application maps.
- [Concepts](docs/concepts.md): glossary for graph terms.
- [Public API](public-api.md): local GET-only HTTP endpoints.

## Optional LLM Providers

The core does not need an LLM. Optional providers are configured through environment variables only:

```bash
export ANTHROPIC_API_KEY=...
export OPENAI_API_KEY=...
export OLLAMA_HOST=http://localhost:11434
export OLLAMA_MODEL=llama3.1
```

When these variables are absent, Flow Engine uses local context exports and deterministic reports.

## Development

```bash
composer install
vendor/bin/phpunit --no-coverage
php bin/quality-gate.php --mode=main
```

## Roadmap

Open source stays focused on the local engine, CLI, MCP, read-only API, Docker, parsers, graph, metrics, cycles, risk, impact, architecture checks, orphans, context exports, and local reports.

See [ROADMAP.md](ROADMAP.md).

## Contributing

Issues and pull requests are welcome. Good first contributions include parser coverage, documentation examples, bug fixtures, and focused tests for graph behavior.

Keep changes local-first, deterministic, and free of required remote services.

## Author

Flow Engine is created and maintained by [Rodrigo Borges](https://roborg.org), a software developer and architect from Brazil focused on codebase understanding, dependency graphs, refactoring, and AI-assisted software maintenance.

More context: [roborg.org](https://roborg.org) | [GitHub](https://github.com/rborges) | [LinkedIn](https://www.linkedin.com/in/rodrigo-borges-dev/)

## License

MIT. See [LICENSE](LICENSE).
