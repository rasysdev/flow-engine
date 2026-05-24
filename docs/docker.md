# Docker

Build the image:

```bash
docker build -t flow-engine .
```

Analyze the current directory:

```bash
docker run --rm -v "$PWD:/workspace" flow-engine analyze /workspace
```

Export context:

```bash
docker run --rm -v "$PWD:/workspace" flow-engine context /workspace --minimal
```

Start the local API:

```bash
docker run --rm -p 8080:8080 -v "$PWD:/workspace" flow-engine api /workspace --host=0.0.0.0 --port=8080
```
