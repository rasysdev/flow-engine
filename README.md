# Flow Engine

Flow Engine builds a deterministic dependency graph from local source code and turns it into facts an AI assistant can use: coupling, cycles, impact, risk, architecture violations, orphan code, reports, and compact context exports.

The core runs locally. The CLI, MCP server, Docker image, read-only HTTP API, parsers, reports, and AI context exports do not require network access or API keys.

## What Is Included

- Local static analysis engine
- CLI
- MCP server for AI clients
- Local read-only HTTP API
- Docker support
- PHP, TypeScript, JavaScript, Python, Go, Dart, and Blade parsers
- Dependency graph, metrics, cycles, risk, impact, architecture checks, and orphan detection
- Context and report exports for AI assistants
- Optional `.claude/` hooks and `CLAUDE.md` guidance

## Requirements

- PHP 8.2+
- Composer

## Install

```bash
composer require rodri/flow-engine
```

For development from source:

```bash
git clone https://github.com/rborges/flow-engine.git
cd flow-engine
composer install
```

## First 5 Minutes

```bash
php bin/engine.php analyze .
php bin/engine.php metrics .
php bin/engine.php cycles .
php bin/engine.php orphans . --audit
php bin/engine.php architecture .
php bin/engine.php context . --minimal
```

## CLI

```bash
php bin/engine.php help
php bin/engine.php nodes .
php bin/engine.php impact . "App\\Service\\OrderService::process"
php bin/engine.php change-risk . --node="App\\Service\\OrderService::process"
php bin/engine.php snapshot . --save=before-change
php bin/engine.php architecture-gate . --baseline=before-change --fail-on=new
```

## MCP Server

```bash
export FLOW_ENGINE_BIN="$PWD/bin/engine.php"
php bin/engine.php mcp
```

The committed `.mcp.json` uses `FLOW_ENGINE_BIN` and does not contain machine-specific paths.
See [docs/mcp.md](docs/mcp.md) for the tool workflow.

## Local Read-Only API

```bash
php bin/engine.php api . --host=127.0.0.1 --port=8080
curl http://127.0.0.1:8080/health
curl http://127.0.0.1:8080/api/v1/metrics
```

Public endpoints are GET-only: `/health`, `/api/v1/metrics`, `/api/v1/cycles`, `/api/v1/architecture`, `/api/v1/orphans`, `/api/v1/nodes`, `/api/v1/edges`, `/api/v1/flow`, `/api/v1/snapshots`, `/api/v1/context`, `/api/v1/bugs`, `/api/v1/appmap`, `/api/v1/appmap-diff`, `/api/v1/compliance-monitor`, `/api/v1/deployment-map`, `/api/v1/devops-map`, `/api/v1/website-map`, and `/api/v1/diagram`.

## Configuration

Create a local config:

```bash
php bin/engine.php init .
```

Then edit `flow-engine.json` to set scan paths, ignored paths, architecture layers, visibility rules, and snapshot retention.

## AI Context

```bash
php bin/engine.php context .
php bin/engine.php context . --minimal
php bin/engine.php context . --entrypoint="App\\Service\\OrderService::process"
```

Optional LLM integrations are enabled only through environment variables:

```bash
export ANTHROPIC_API_KEY=...
export OPENAI_API_KEY=...
export OLLAMA_HOST=http://localhost:11434
export OLLAMA_MODEL=llama3.1
```

Without those variables, Flow Engine still exports context and reports locally.

## Docker

```bash
docker build -t flow-engine .
docker run --rm -v "$PWD:/workspace" flow-engine analyze /workspace
```

## Claude Code Hooks

The `.claude/` directory is optional. To enable the example hooks, copy:

```bash
cp .claude/settings.example.json .claude/settings.json
```

Local settings remain ignored by Git.

## Development

```bash
composer install
vendor/bin/phpunit --no-coverage
php bin/quality-gate.php --mode=main
```

## License

MIT. See [LICENSE](LICENSE).
