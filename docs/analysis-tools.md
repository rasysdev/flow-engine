# Analysis Tools

## Metrics

```bash
php bin/engine.php metrics .
```

Use metrics to find hotspots and heavily coupled nodes.

## Cycles

```bash
php bin/engine.php cycles .
```

Use cycle reports to identify dependency loops and refactor targets.

## Architecture

```bash
php bin/engine.php architecture .
```

Architecture checks compare detected dependencies against configured layer rules.

## Orphans

```bash
php bin/engine.php orphans . --audit
```

Audit mode adds classification and evidence so candidates can be reviewed safely.

## Impact And Risk

```bash
php bin/engine.php impact . "App\\Service\\OrderService::process"
php bin/engine.php change-risk . --node="App\\Service\\OrderService::process"
```

Impact shows affected nodes. Risk combines coupling, cycles, violations, and other graph factors.

## Reports For AI

```bash
php bin/engine.php context . --minimal
php bin/engine.php context . --entrypoint="App\\Service\\OrderService::process"
```
