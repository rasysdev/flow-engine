# Vision

Flow Engine gives AI assistants and developers a factual map of a codebase.

Static source reads are useful, but they are easy to misread at scale. Flow Engine builds a deterministic dependency graph first, then derives metrics, cycles, architecture findings, orphan candidates, impact, and risk from that graph.

The goal is not to replace human judgment. The goal is to make every review, refactor, and architecture discussion start from the same local facts.

## Principles

- Local-first: source code stays on the machine running the engine.
- Deterministic: reports come from graph analysis, not guesswork.
- Scriptable: every important workflow works from the CLI.
- AI-friendly: exports are compact enough to fit into assistant context.
- Open core: the public repository remains usable without external services.
