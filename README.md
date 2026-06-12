<p align="center">
  <img src="docs/assets/flow-engine-logo-text.svg" alt="Flow Engine" width="620">
</p>

<p align="center">
  <strong>Local dependency graph and factual context for AI-assisted software work.</strong>
</p>

<p align="center">
  <a href="docs/README.md">Documentation</a> ·
  <a href="docs/CLI_COMMANDS.md">CLI</a> ·
  <a href="docs/mcp.md">MCP</a> ·
  <a href="docs/docker.md">Docker</a> ·
  <a href="docs/configuration.md">Configuration</a> ·
  <a href="public-api.md">Public API</a> ·
  <a href="ROADMAP.md">Roadmap</a>
</p>

<p align="center">
  <a href="https://github.com/rborges/flow-engine/actions/workflows/ci.yml"><img src="https://github.com/rborges/flow-engine/actions/workflows/ci.yml/badge.svg" alt="CI"></a>
  <img alt="License: MIT" src="https://img.shields.io/badge/license-MIT-64b6ac">
  <img alt="PHP 8.3+" src="https://img.shields.io/badge/PHP-8.3%2B-777bb4">
  <img alt="MCP server" src="https://img.shields.io/badge/MCP-server-d6a84f">
  <img alt="Docker" src="https://img.shields.io/badge/Docker-ready-2496ed">
  <img alt="Local first" src="https://img.shields.io/badge/local--first-no%20required%20API%20key-e06c5f">
</p>

<p align="center">
  <img src="docs/assets/flow-engine-graph.svg" alt="Flow Engine dependency graph" width="860">
</p>

**Flow Engine** analyzes a codebase on your machine and turns it into a graph an AI assistant can trust:
files, symbols, dependencies, cycles, impact, risk, architecture rules, orphan code, bugs,
snapshots, and compact context exports.

It is built for local use first. The CLI, MCP server, Docker image, read-only API, parsers,
reports, and context exports work without network access, telemetry, accounts, or API keys.

You use Flow Engine from this repository, then point it at the project you want to understand.
In the examples below, `<project>` means your application repository, such as `C:\dev\my-app`
on Windows or `/home/me/my-app` on Linux.

## Why Flow Engine

AI coding tools are strongest when they have precise codebase facts instead of a prompt full
of guesses. Flow Engine gives them a deterministic map of the system before they suggest changes.

Use it to:

- Ask an AI assistant about a codebase with grounded context.
- Find cycles, hotspots, or orphaned code before a refactor.
- Estimate impact and risk for a class, method, route, or module.
- Keep local architecture rules enforceable in CI.
- Export reports for code review, planning, and maintenance.
- Expose a local MCP toolset to Claude, Codex, Cline, Continue, or any MCP-compatible client.

## What It Includes

| Area | Included |
| --- | --- |
| Engine | Local static analysis, graph construction, metrics, cycles, impact, risk, architecture checks, orphan detection |
| Languages | PHP, TypeScript, JavaScript, Python, Go, Dart, and Blade |
| Interfaces | CLI, MCP server, local read-only HTTP API, Docker |
| AI context | Minimal/full context exports, entrypoint-focused context, reports, Mermaid text output |
| Change tracking | Filesystem snapshots, compare, drift, cleanup, architecture gate |
| Privacy | No required remote service, no required API key, no required telemetry, no account system |

## Feature Benefits

- [Local engine](docs/overview.md): build a deterministic dependency graph before changing code, so decisions are based on facts instead of guesses.
- [CLI](docs/CLI_COMMANDS.md): run every analysis command from a terminal, script, or CI job.
- [MCP server](docs/mcp.md): let AI assistants inspect the codebase through local tools instead of relying on pasted context.
- [Read-only API](public-api.md): expose local graph facts to scripts and local tools without write endpoints.
- [Docker](docs/docker.md): run Flow Engine on Windows, Linux, macOS, and CI without installing PHP locally.
- [Metrics](docs/analysis-tools.md#metrics): find hotspots, coupling pressure, and files that deserve attention before a refactor.
- [Cycles](docs/analysis-tools.md#cycles): locate dependency loops that make changes harder and tests more fragile.
- [Architecture checks](docs/analysis-tools.md#architecture): enforce local layer rules and catch unwanted dependencies early.
- [Orphans](docs/analysis-tools.md#orphans): find code that may be unused, with audit evidence before deletion.
- [Impact and risk](docs/analysis-tools.md#impact-and-risk): estimate what a change can affect before editing a class, method, route, or module.
- [Flow tracing](docs/flow-tracing.md): follow upstream and downstream dependencies from one node to understand feature behavior.
- [AI context exports](docs/analysis-tools.md#reports-for-ai): generate compact, factual Markdown for AI-assisted maintenance.
- [Snapshots and gates](docs/CLI_COMMANDS.md#snapshots-and-gates): save local baselines, compare drift, and fail CI on new architecture issues.
- [Diagrams and app maps](docs/system-diagrams.md): generate Mermaid diagrams and multi-service maps from local code facts.
- [Configuration](docs/configuration.md): tune scan paths, ignores, languages, architecture layers, and snapshot retention per project.

## Start Here

Choose one setup:

- **Windows, easiest path:** install Git, PHP 8.3+, Composer, and use PowerShell.
- **Linux/macOS:** install Git, PHP 8.3+, Composer, and use your shell.
- **Docker only:** install Docker Desktop or Docker Engine, then build the image locally.

Flow Engine is not required to be installed inside the project you are analyzing. Clone it once, then run commands against any local repository.

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

Composer package install will be documented here once the package is published.

## Quick Start

Run these commands from the Flow Engine directory and replace `<project>` with the project you want to analyze.

Windows PowerShell:

```powershell
php .\bin\engine.php analyze C:\dev\my-app
php .\bin\engine.php metrics C:\dev\my-app
php .\bin\engine.php cycles C:\dev\my-app
php .\bin\engine.php orphans C:\dev\my-app --audit
php .\bin\engine.php architecture C:\dev\my-app
php .\bin\engine.php context C:\dev\my-app --minimal
```

Linux/macOS:

```bash
php bin/engine.php analyze /home/me/my-app
php bin/engine.php metrics /home/me/my-app
php bin/engine.php cycles /home/me/my-app
php bin/engine.php orphans /home/me/my-app --audit
php bin/engine.php architecture /home/me/my-app
php bin/engine.php context /home/me/my-app --minimal
```

If you are already inside the project you want to analyze, use `.` as the project path:

Windows PowerShell:

```powershell
php C:\dev\flow-engine\bin\engine.php context . --minimal
```

Linux/macOS:

```bash
php /home/me/flow-engine/bin/engine.php context . --minimal
```

The `context --minimal` command prints compact, factual context you can paste into an AI assistant.
MCP is better for repeated use because the assistant can call Flow Engine tools directly.

## Docker Quick Start

Build the local image from the Flow Engine repository:

```bash
docker build -t flow-engine .
```

Analyze a project on Windows PowerShell:

```powershell
docker run --rm -v "C:\dev\my-app:/workspace:ro" flow-engine analyze /workspace
docker run --rm -v "C:\dev\my-app:/workspace:ro" flow-engine context /workspace --minimal
```

Analyze the current directory in PowerShell:

```powershell
docker run --rm -v "${PWD}:/workspace:ro" flow-engine metrics /workspace
```

Analyze a project on Linux/macOS:

```bash
docker run --rm -v "/home/me/my-app:/workspace:ro" flow-engine analyze /workspace
docker run --rm -v "$PWD:/workspace:ro" flow-engine metrics /workspace
```

See [docs/docker.md](docs/docker.md) for Docker Compose and API examples.

## Common Workflows

The same commands work on Windows with `php .\bin\engine.php` and a Windows path, or on Linux/macOS with `php bin/engine.php` and a Unix path.

Inspect the project graph:

```bash
php bin/engine.php nodes <project>
php bin/engine.php flow <project>
php bin/engine.php metrics <project>
```

Plan a change:

```bash
php bin/engine.php impact <project> "App\\Service\\OrderService::process"
php bin/engine.php change-risk <project> --node="App\\Service\\OrderService::process"
php bin/engine.php context <project> --entrypoint="App\\Service\\OrderService::process"
```

Track architecture drift:

```bash
php bin/engine.php snapshot <project> --save=before-change
php bin/engine.php architecture-gate <project> --baseline=before-change --fail-on=new
```

Generate local reports:

```bash
php bin/engine.php bugs <project>
php bin/engine.php diagram <project> --view=class
```

For multi-service maps, see [System diagrams](docs/system-diagrams.md).

## MCP Server

Flow Engine can run as a local MCP server so AI clients can inspect your codebase through tools instead of relying on pasted context.

![Flow Engine MCP calls in an agent client](docs/assets/flow-engine-terminal.svg)

The MCP server runs over stdio. Configure your MCP client to run the `mcp` command from this repository.

Windows PowerShell:

```powershell
$env:FLOW_ENGINE_BIN = "$PWD\bin\engine.php"
php .\bin\engine.php mcp
```

Linux/macOS:

```bash
export FLOW_ENGINE_BIN="$PWD/bin/engine.php"
php bin/engine.php mcp
```

The committed `.mcp.json` uses `FLOW_ENGINE_BIN`, so it does not contain machine-specific paths.
For most clients, the command is `php`, and the arguments are the full path to `bin/engine.php`
followed by `mcp`.

See [docs/mcp.md](docs/mcp.md) for setup, available tools, and an example system prompt.

## Local Read-Only API

Start the API for a project, then query it from the same machine.

Windows PowerShell:

```powershell
php .\bin\engine.php api C:\dev\my-app --host=127.0.0.1 --port=8080
Invoke-RestMethod http://127.0.0.1:8080/health
Invoke-RestMethod http://127.0.0.1:8080/api/v1/metrics
```

Linux/macOS:

```bash
php bin/engine.php api /home/me/my-app --host=127.0.0.1 --port=8080
curl http://127.0.0.1:8080/health
curl http://127.0.0.1:8080/api/v1/metrics
curl http://127.0.0.1:8080/api/v1/context
```

Public endpoints are GET-only:

- `/health`
- `/api/v1/metrics`
- `/api/v1/cycles`
- `/api/v1/architecture`
- `/api/v1/orphans`
- `/api/v1/nodes`
- `/api/v1/edges`
- `/api/v1/flow`
- `/api/v1/snapshots`
- `/api/v1/context`
- `/api/v1/bugs`
- `/api/v1/appmap`
- `/api/v1/appmap-diff`
- `/api/v1/compliance-monitor`
- `/api/v1/deployment-map`
- `/api/v1/devops-map`
- `/api/v1/website-map`
- `/api/v1/diagram`

POST requests return `405`.

## Configuration

Create a starter config in the project you want to analyze:

Windows PowerShell:

```powershell
php .\bin\engine.php init C:\dev\my-app
```

Linux/macOS:

```bash
php bin/engine.php init /home/me/my-app
```

Then edit `flow-engine.json` to define:

- Paths to scan and paths to ignore.
- Architecture layers and allowed dependencies.
- Visibility and entrypoint rules.
- Snapshot retention.
- Optional external project roots for local audits.

Do not store secrets in `flow-engine.json`. Optional LLM providers are configured through environment variables only.

See [docs/configuration.md](docs/configuration.md).

## Documentation Map

- [Getting started](docs/getting-started.md): first run on Windows, Linux/macOS, or Docker.
- [Overview](docs/overview.md): what Flow Engine does and where each interface fits.
- [CLI commands](docs/CLI_COMMANDS.md): command reference with common examples.
- [MCP server](docs/mcp.md): connect Flow Engine to MCP-compatible AI clients.
- [Docker](docs/docker.md): run analysis and the local API in containers.
- [Configuration](docs/configuration.md): `flow-engine.json` fields and examples.
- [Analysis tools](docs/analysis-tools.md): metrics, cycles, architecture, orphans, impact, risk, and reports.
- [Concepts](docs/concepts.md): glossary for graph terms.

## Optional LLM Providers

The core does not need an LLM. You can still analyze, export, serve MCP tools, and run the read-only API without any provider key.

Optional providers are opt-in:

```bash
export ANTHROPIC_API_KEY=...
export OPENAI_API_KEY=...
export OLLAMA_HOST=http://localhost:11434
export OLLAMA_MODEL=llama3.1
```

When these variables are not present, Flow Engine falls back to local context exports and deterministic reports.

## Docker

Use this section when you want a little more than the quick Docker example above.

Analyze a mounted project:

Windows PowerShell:

```powershell
docker build -t flow-engine .
docker run --rm -v "C:\dev\my-app:/workspace:ro" flow-engine analyze /workspace
```

Linux/macOS:

```bash
docker build -t flow-engine .
docker run --rm -v "$PWD:/workspace:ro" flow-engine analyze /workspace
```

Run the local API:

```bash
docker compose up --build
curl http://127.0.0.1:8080/health
```

See [docs/docker.md](docs/docker.md).

## Security And Privacy

- Local-first: analysis runs against local files.
- No required telemetry.
- No account or payment code.
- No required external runtime.
- API is local and read-only.
- LLM providers are optional and enabled only through environment variables.
- `.claude/` settings are examples; local settings are ignored by Git.

To enable the example Claude Code hooks:

```bash
cp .claude/settings.example.json .claude/settings.json
```

## Development

```bash
composer install
vendor/bin/phpunit --no-coverage
php bin/quality-gate.php --mode=main
```

Docker-only validation:

```bash
docker run --rm -v "$PWD":/src:ro -w /work composer:2 sh -lc \
  'cp -a /src/. . && composer validate --strict && composer install --no-interaction --no-progress && vendor/bin/phpunit --no-coverage'
```

## Roadmap

Open source stays focused on the local engine, CLI, MCP, read-only API, Docker, parsers,
graph, metrics, cycles, risk, impact, architecture checks, orphans, context exports,
and local reports.

Future paid or closed work will be tracked separately and will not be required for the local open-source core.

See [ROADMAP.md](ROADMAP.md).

## Contributing

Issues and pull requests are welcome. Good first contributions include parser coverage, documentation examples, bug fixtures, and focused tests for graph behavior.

Before opening a PR:

```bash
composer install
vendor/bin/phpunit --no-coverage
php bin/quality-gate.php --mode=main
```

Keep changes local-first, deterministic, and free of required remote services.

## Author / Autor

Flow Engine is created and maintained by [Rodrigo Borges](https://roborg.org), a software
developer and architect from Brazil focused on codebase understanding, dependency graphs,
refactoring, and AI-assisted software maintenance.

O Flow Engine foi criado e mantido por [Rodrigo Borges](https://roborg.org), desenvolvedor
e arquiteto de software no Brasil, com foco em entendimento de codebases, grafos de dependencia,
refatoracao e manutencao de software assistida por IA.

More context: [roborg.org](https://roborg.org) · [GitHub](https://github.com/rborges) · [LinkedIn](https://www.linkedin.com/in/rodrigo-borges-dev/)

## License

MIT. See [LICENSE](LICENSE).
