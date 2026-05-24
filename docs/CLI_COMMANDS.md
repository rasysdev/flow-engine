# CLI Commands

Run commands with:

```bash
php bin/engine.php <command> [arguments] [options]
```

## Setup

- `init <path>`: create `flow-engine.json`.
- `doctor <path>`: validate configuration.
- `analyze <path>`: build the dependency graph.

## Analysis

- `metrics <path>`: coupling, fan-in, fan-out, and hotspots.
- `complexity <path>`: complexity findings.
- `cycles <path>`: dependency cycles.
- `architecture <path>`: architecture rule violations.
- `orphans <path> [--audit]`: orphan candidates and evidence.
- `bugs <path> [--min-score=N] [--type=TYPE]`: static bug patterns.
- `solid <path>`: SOLID findings.
- `patterns <path>`: design pattern detection.
- `entrypoints <path>`: framework and application entrypoints.

## Node Inspection

- `nodes <path>`: list graph nodes.
- `flow <path>`: export nodes and edges.
- `inputs <path> <node>`: show inputs and return type.
- `impact <path> <node>`: change impact.
- `impact-report <path> --node=<node>`: detailed impact report.
- `change-risk <path> --node=<node>`: deterministic risk score.
- `trace <path> --node=<node>`: upstream and downstream dependencies.
- `explain <path> <node>`: visibility and governance explanation.

## AI Context

- `context <path> [--minimal]`: export compact context.
- `context <path> --entrypoint=<node>`: focused context.
- `ask "<question>" <path>`: ask through an optional LLM provider.
- `interpret <path> --type=<type>`: optional interpretation for graph reports.

## Refactoring And Remediation

- `refactor-plan <path> --node=<node>`: graph-backed refactor plan.
- `refactor-safety <path> --node=<node>`: safety assessment.
- `refactor-execute <path> --plan=<label> --step=<N>`: local step guidance.
- `refactor-validate <path> --plan=<label> --step=<N>`: validate step completion.
- `refactor-pr <path> --plan=<label>`: generate local PR text.
- `remediation-proposals <path>`: generate local remediation proposals.
- `remediation-approve <path> --plan=<label> --id=<proposal_id>`: mark a proposal approved.
- `remediation-status <path> --plan=<label>`: inspect approval status.

## Snapshots And Gates

- `snapshot <path> --save=<label>`: save current reports.
- `snapshot <path> --compare=<label>`: compare with a saved baseline.
- `snapshot <path> --list`: list local snapshots.
- `drift <path> --baseline=<label>`: detect drift from a baseline.
- `cleanup <path> --older-than=<days>`: delete old snapshots.
- `cleanup <path> --keep-last=<N>`: keep the newest snapshots.
- `architecture-gate <path> --baseline=<label> --fail-on=new`: CI-friendly local gate.

## Servers And Integrations

- `api <path>`: start the local read-only HTTP API.
- `mcp`: start the MCP stdio server.
- `watch <path>`: rerun analysis on file changes.

## Examples

```bash
php bin/engine.php analyze .
php bin/engine.php metrics .
php bin/engine.php context . --minimal
php bin/engine.php snapshot . --save=before-refactor
php bin/engine.php architecture-gate . --baseline=before-refactor --fail-on=new
```
