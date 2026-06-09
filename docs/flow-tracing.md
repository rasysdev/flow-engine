# Flow Tracing

Flow tracing follows dependencies from one node so you can answer questions like:

- What does this controller action call?
- Who calls this service method?
- What code is connected to this feature?

## Find A Node

If you do not know the exact node ID, list nodes first:

```bash
php bin/engine.php nodes <project>
```

Then trace the node:

```bash
php bin/engine.php trace <project> --node="App\\Service\\OrderService::process" --direction=both
```

On Windows PowerShell:

```powershell
php .\bin\engine.php trace C:\dev\my-app --node="App\\Service\\OrderService::process" --direction=both
```

## Directions

- `downstream`: what this node calls.
- `upstream`: what calls this node.
- `both`: connected dependencies in both directions.

Examples:

```bash
php bin/engine.php trace <project> --node="App\\Http\\Controller\\OrderController::store" --direction=downstream
php bin/engine.php trace <project> --node="App\\Repository\\OrderRepository::save" --direction=upstream
php bin/engine.php trace <project> --node="App\\Service\\OrderService::process" --direction=both
```

## Output

The command prints JSON with the starting node, direction, reached depth, nodes, edges, and paths. Save it to a file when you want to inspect or share it locally:

```bash
php bin/engine.php trace <project> --node="App\\Service\\OrderService::process" --direction=both > trace.json
```

## Related Commands

- `impact <project> <node>`: show affected nodes.
- `change-risk <project> --node=<node>`: score change risk.
- `context <project> --entrypoint=<node>`: export focused AI context.
- `diagram <project> --view=flowchart --entrypoint=<node>`: generate a Mermaid flowchart.
