# Configuration

Flow Engine reads `flow-engine.json` from the analyzed project.

Create one with:

```bash
php bin/engine.php init .
```

Common fields:

```json
{
  "paths": ["src"],
  "exclude": ["vendor", "node_modules", ".git"],
  "languages": ["php", "typescript", "javascript", "python", "go", "dart", "blade"],
  "architecture": {
    "layers": {
      "Domain": "src/Domain",
      "Application": "src/Application",
      "Infrastructure": "src/Infrastructure"
    },
    "rules": [
      { "from": "Domain", "notTo": ["Infrastructure"] }
    ]
  },
  "snapshots": {
    "retention": 20
  }
}
```

LLM providers are optional and configured only through environment variables. Core analysis, API, MCP, and context export work without them.
