# Public API

Flow Engine exposes a local read-only HTTP API for tools that need structured graph data.

Use the API when another local process needs graph facts over HTTP. For direct terminal use, prefer the CLI.

## Start The API

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
```

Only `GET` is supported. Other methods return `405`.

## Benefits

- Query analysis results from local scripts and tools.
- Keep the API read-only while mutating workflows stay in the CLI.
- Serve graph data without accounts, telemetry, or external services.

## Endpoints

- `GET /health`
- `GET /api/v1/metrics`
- `GET /api/v1/cycles`
- `GET /api/v1/architecture`
- `GET /api/v1/orphans`
- `GET /api/v1/nodes`
- `GET /api/v1/edges`
- `GET /api/v1/flow`
- `GET /api/v1/snapshots`
- `GET /api/v1/context`
- `GET /api/v1/bugs`
- `GET /api/v1/appmap`
- `GET /api/v1/appmap-diff`
- `GET /api/v1/compliance-monitor`
- `GET /api/v1/deployment-map`
- `GET /api/v1/devops-map`
- `GET /api/v1/website-map`
- `GET /api/v1/diagram`

## Notes

- The API analyzes the local project path passed to the `api` command.
- Responses are JSON arrays or objects.
- The API is intended for localhost usage and tool integration.
- Mutating workflows remain CLI-only.
