# Public API

Flow Engine exposes a local read-only HTTP API for tools that need structured graph data.

Start it with:

```bash
php bin/engine.php api . --host=127.0.0.1 --port=8080
```

Only `GET` is supported. Other methods return `405`.

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

- The API analyzes the configured local project path.
- Responses are JSON arrays or objects.
- The API is intended for localhost usage and tool integration.
- Mutating workflows remain CLI-only.
