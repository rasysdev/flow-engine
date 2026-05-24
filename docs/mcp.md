# MCP Server

Flow Engine can run as a Model Context Protocol server over stdio.

```bash
export FLOW_ENGINE_BIN="$PWD/bin/engine.php"
php bin/engine.php mcp
```

The repository includes `.mcp.json`:

```json
{
  "mcpServers": {
    "flow-engine": {
      "type": "stdio",
      "command": "php",
      "args": ["${FLOW_ENGINE_BIN}", "mcp"]
    }
  }
}
```

Recommended AI workflow:

1. Call `flow_map` to understand project shape.
2. Call `flow_find` when the exact node ID is unknown.
3. Call `flow_lookup` for focused context.
4. Call `flow_context`, `flow_impact`, or `flow_risk` for targeted analysis.

The MCP server is read-only and uses local graph data.
