# Docker

Use Docker when you want to run Flow Engine without installing PHP and Composer locally.

Build the image from the Flow Engine repository:

```bash
docker build -t flow-engine .
```

## Windows PowerShell

Analyze a project:

```powershell
docker run --rm -v "C:\dev\my-app:/workspace:ro" flow-engine analyze /workspace
docker run --rm -v "C:\dev\my-app:/workspace:ro" flow-engine metrics /workspace
docker run --rm -v "C:\dev\my-app:/workspace:ro" flow-engine context /workspace --minimal
```

Analyze the current directory:

```powershell
docker run --rm -v "${PWD}:/workspace:ro" flow-engine analyze /workspace
```

Start the local API:

```powershell
docker run --rm -p 8080:8080 -v "C:\dev\my-app:/workspace:ro" flow-engine api /workspace --host=0.0.0.0 --port=8080
Invoke-RestMethod http://127.0.0.1:8080/health
```

## Linux/macOS

Analyze the current directory:

```bash
docker run --rm -v "$PWD:/workspace:ro" flow-engine analyze /workspace
```

Export context:

```bash
docker run --rm -v "$PWD:/workspace:ro" flow-engine context /workspace --minimal
```

Start the local API:

```bash
docker run --rm -p 8080:8080 -v "$PWD:/workspace:ro" flow-engine api /workspace --host=0.0.0.0 --port=8080
curl http://127.0.0.1:8080/health
```

## Docker Compose

The repository includes a Compose file for local API testing:

```bash
docker compose up --build
```

Then open:

```text
http://127.0.0.1:8080/health
```

## Notes

- Mount analyzed projects read-only unless a command needs to create `flow-engine.json` or write local snapshots.
- Use a normal writable mount if you run `init`, `snapshot`, `cleanup`, or other commands that write files.
