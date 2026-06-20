# Changelog

## 0.1.2 - Guardrail fixes

- `DocumentationUpdater` now throws a clear exception for a missing or unreadable docs file instead of emitting a PHP warning.
- `flow_infra_map` preserves Compose volumes: YAML key detection was tightened so `- host:container` volume entries are no longer misread as map keys.
- Restored the `simple-project` test fixture and added a deterministic no-LLM `refactor-plan` fallback plus markdown output for `refactor-validate` (reconnecting `MarkdownFormatter::formatRefactorValidation` to a real command).

## 0.1.1 - Passes its own gate

- Added a GitHub Actions self-gate workflow (tests, architecture, unclassified nodes, and cross-class cycles) with a CI status badge; the engine now passes its own analysis gate, tested on PHP 8.3, 8.4, and 8.5.
- Classified every source namespace into an architecture layer (no remaining "Unknown" nodes) and inverted Application -> Infrastructure dependencies through ports.
- Recalibrated the analyzers to stop flagging deliberate patterns: graceful-degradation fallbacks and intra-class mutual recursion are now reported as INFO, and change risk is capped at HIGH for nodes with no callers and zero blast radius.
- Removed dead code surfaced by orphan analysis.
- Built the deployment map in a single catalog pass, avoiding a duplicate catalog load and Docker topology computation.

## 0.1.0 - Initial open-source release

- Published the local-first Flow Engine core under the MIT license.
- Included CLI, MCP server, Docker support, read-only local API, parsers, dependency graph analysis,
  metrics, cycles, risk, impact, architecture checks, orphan detection, and AI context exports.
- Removed private release material, operational archives, UI artifacts, and non-core deployment notes.
- Made `.claude/` integration opt-in through `settings.example.json`.
- Kept LLM providers optional; core analysis and exports work without network access or API keys.
