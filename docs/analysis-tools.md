# Analysis Tools

Replace `<project>` with the path to the project you want to analyze. On Windows, that can be `C:\dev\my-app`. On Linux/macOS, that can be `/home/me/my-app` or `.`.

## Metrics

```bash
php bin/engine.php metrics <project>
```

Use metrics to find hotspots and heavily coupled nodes.

Benefits:

- Find files or symbols that attract many dependencies.
- Spot high fan-out code that may be doing too much.
- Prioritize refactors using graph facts instead of intuition.
- Create a baseline before a large cleanup.

## Cycles

```bash
php bin/engine.php cycles <project>
```

Use cycle reports to identify dependency loops and refactor targets.

Benefits:

- See exact dependency loops instead of hunting through imports manually.
- Reduce code that is hard to test, move, or delete.
- Pick smaller refactor targets by starting with the cycle members.
- Track whether a cleanup actually removed the loop.

## Architecture

```bash
php bin/engine.php architecture <project>
```

Architecture checks compare detected dependencies against configured layer rules.

Benefits:

- Make architecture rules visible and executable.
- Catch new layer violations before they become normal.
- Use the same checks locally and in CI.
- Explain why a dependency crosses a boundary.

## Orphans

```bash
php bin/engine.php orphans <project> --audit
```

Audit mode adds classification and evidence so candidates can be reviewed safely.

Benefits:

- Find code with no detected incoming path.
- Separate deletion candidates from framework or entrypoint false positives.
- Review evidence before removing anything.
- Reduce maintenance load from dead or unreachable code.

## Impact And Risk

```bash
php bin/engine.php impact <project> "App\\Service\\OrderService::process"
php bin/engine.php change-risk <project> --node="App\\Service\\OrderService::process"
```

Impact shows affected nodes. Risk combines coupling, cycles, violations, and other graph factors.

Benefits:

- Estimate blast radius before changing a method, class, route, or module.
- Identify downstream code that may need tests.
- Explain why a change is risky in concrete graph terms.
- Compare safer and riskier refactor targets.

## Trace

```bash
php bin/engine.php trace <project> --node="App\\Service\\OrderService::process" --direction=both
```

Trace follows dependencies upstream, downstream, or both from a node.

Benefits:

- Understand what happens after an entrypoint or service method runs.
- Find callers of a low-level method before changing it.
- Discover feature boundaries by following connected code.
- Save trace JSON for review or local tooling.

## Bugs

```bash
php bin/engine.php bugs <project>
```

Bug detection reports static patterns that may deserve review.

Benefits:

- Surface likely defects without running the application.
- Filter findings by score or type when focusing a review.
- Add focused tests around suspicious code paths.
- Use local reports during maintenance before opening a pull request.

## Entrypoints

```bash
php bin/engine.php entrypoints <project>
```

Entrypoints list framework and application boundaries Flow Engine can detect.

Benefits:

- Find routes, controllers, commands, and other starting points.
- Pick a useful node for tracing or focused context export.
- Improve AI prompts by grounding them around real application boundaries.

## Reports For AI

```bash
php bin/engine.php context <project> --minimal
php bin/engine.php context <project> --entrypoint="App\\Service\\OrderService::process"
```

Use context exports when you want to paste facts into an assistant. Use MCP when the assistant should request facts on demand.

Benefits:

- Give AI assistants concise facts instead of broad, noisy file dumps.
- Focus context around an entrypoint or namespace.
- Work without an LLM provider key by exporting Markdown locally.
- Keep codebase understanding reproducible across sessions.

## Snapshots And Gates

```bash
php bin/engine.php snapshot <project> --save=before-change
php bin/engine.php drift <project> --baseline=before-change
php bin/engine.php architecture-gate <project> --baseline=before-change --fail-on=new
```

Snapshots save local baselines for later comparison.

Benefits:

- Compare graph and architecture drift over time.
- Keep local evidence before and after a refactor.
- Fail CI when new architecture issues appear.
- Clean old snapshots while keeping recent baselines.
