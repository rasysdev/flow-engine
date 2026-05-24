# Flow Engine Project Context

## Useful Commands

```bash
php bin/engine.php analyze .
php bin/engine.php context . --minimal
php bin/engine.php metrics .
php bin/engine.php cycles .
php bin/engine.php architecture .
php bin/engine.php orphans . --audit
vendor/bin/phpunit --no-coverage
```

## Architecture Rules

- Domain code should not depend on Infrastructure.
- Use cases orchestrate domain services and repositories.
- CLI commands implement `Command`.
- DTOs should be explicit and serializable through `toArray()` where applicable.
- LLM access goes through the `LLMProvider` interface; keep the null provider path working.

## MCP

The committed `.mcp.json` expects `FLOW_ENGINE_BIN` to point at `bin/engine.php`.

```bash
export FLOW_ENGINE_BIN="$PWD/bin/engine.php"
```

## Optional Hooks

`.claude/settings.example.json` shows how to enable local context injection hooks. Copy it to `.claude/settings.json` only when you want those hooks active locally.
