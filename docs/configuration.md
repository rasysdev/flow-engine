# Configuration

Flow Engine reads `flow-engine.json` from the project being analyzed. This file tells the engine what to scan, what to ignore, and which architecture rules should be checked.

Create one on Windows PowerShell:

```powershell
php .\bin\engine.php init C:\dev\my-app
```

Create one on Linux/macOS:

```bash
php bin/engine.php init /home/me/my-app
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

## Fields

- `paths`: directories to scan inside the analyzed project.
- `exclude`: directories or files to ignore.
- `languages`: language parsers to enable.
- `architecture.layers`: named areas in your codebase.
- `architecture.rules`: allowed or forbidden dependencies between layers.
- `snapshots.retention`: how many local snapshots to keep by default.

## Architecture Rules

Use architecture rules to catch dependencies that should not exist. For example, this rule keeps domain code from depending on infrastructure code:

```json
{
  "architecture": {
    "layers": {
      "Domain": "src/Domain",
      "Infrastructure": "src/Infrastructure"
    },
    "rules": [
      { "from": "Domain", "notTo": ["Infrastructure"] }
    ]
  }
}
```

Then run:

```bash
php bin/engine.php architecture <project>
```

## Secrets

LLM providers are optional and configured only through environment variables. Core analysis, API, MCP, and context export work without them.

Do not store API keys, tokens, or machine-specific secrets in `flow-engine.json`.
