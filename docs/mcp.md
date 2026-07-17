# MCP Server

Flow Engine can run as a Model Context Protocol server over stdio.

The server is local and read-only. It lets an AI client ask for graph facts, context, impact, risk, and lookup results without pasting large reports into chat.

## Run Locally

Windows PowerShell:

```powershell
$env:FLOW_ENGINE_BIN = "$PWD\bin\engine.php"
php .\bin\engine.php mcp
```

Linux/macOS:

```bash
export FLOW_ENGINE_BIN="$PWD/bin/engine.php"
php bin/engine.php mcp
```

For most MCP clients, configure:

- Command: `php`
- Arguments: path to `bin/engine.php`, then `mcp`

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

## Claude Code Permissions

Every Flow Engine MCP tool is read-only, so you may prefer to pre-approve the
whole server instead of confirming each call.

In Claude Code, add a server-level allow rule to the project's
`.claude/settings.json`, or to `~/.claude/settings.json` to apply it to every
project:

```json
{
  "permissions": {
    "allow": ["mcp__flow-engine"]
  }
}
```

The rule `mcp__flow-engine` matches all tools from the server;
`mcp__flow-engine__*` is an equivalent wildcard form. To allow a single tool
instead, use the full tool name, for example `mcp__flow-engine__flow_map`.
See the [Claude Code permissions reference](https://code.claude.com/docs/en/permissions#mcp)
for the rule syntax.

Note that the tools accept arbitrary local paths in their `project` argument,
not just the current repository. A user-wide allow rule therefore lets any
Claude Code session analyze any readable source tree without a prompt. If you
work with untrusted repositories, prefer the per-project rule.

Known limitation: Claude Code plan mode hard-blocks MCP tools that do not
declare `readOnlyHint` in their `tools/list` annotations ("Cannot call ...
while in plan mode"), even when an allow rule matches, and this also applies
to subagents spawned from a plan mode session. Flow Engine releases that
include [#7](https://github.com/rborges/flow-engine/pull/7) declare the
read-only annotations on every tool, so plan mode falls back to the regular
permission flow and the allow rules above apply — verified on Claude Code
2.1.212 for the main agent and for Agent/Task subagents in both normal and
plan mode. On older Flow Engine releases, interactive plan mode escalates each
call to a manual approval prompt, and non-interactive (`-p`) runs deny it
outright; approve each call, run the tools outside plan mode, or upgrade.
Older Claude Code versions also had reports of subagents not
inheriting allow rules at all
([anthropics/claude-code#5465](https://github.com/anthropics/claude-code/issues/5465),
[anthropics/claude-code#14714](https://github.com/anthropics/claude-code/issues/14714));
if subagent calls still prompt for you, update Claude Code before changing
permission rules.

## Recommended AI Workflow

1. Call `flow_map` to understand project shape.
2. Call `flow_find` when the exact node ID is unknown.
3. Call `flow_lookup` for focused context.
4. Call `flow_context`, `flow_impact`, or `flow_risk` for targeted analysis.

## Example System Prompt

Use a prompt like this in an MCP-compatible AI client when you want the assistant to rely on Flow Engine facts before proposing code changes:

```text
You are working in a repository that has Flow Engine available through MCP.

Before making architectural, refactoring, or debugging recommendations, use the
Flow Engine tools to inspect the codebase. Prefer deterministic graph facts over
guessing from filenames.

Workflow:
- Start with flow_map to understand the project structure.
- Use flow_infra_map when the repository is mostly Docker, scripts, static files,
  proxy config, or other infrastructure rather than application code.
- Use flow_find when you need the exact node ID for a class, method, function,
  route, command, or module.
- Use flow_lookup for focused details about a specific node.
- Use flow_context when you need compact project context for a task.
- Use flow_impact before recommending changes to a node.
- Use flow_risk before changing highly connected or architecture-sensitive code.

When answering:
- Cite the Flow Engine findings that influenced your recommendation.
- Distinguish facts from assumptions.
- If Flow Engine cannot find enough evidence, say what is missing.
- Do not assume remote services, accounts, telemetry, or API keys are available.
- Keep recommendations local-first and compatible with the existing codebase.
```

For smaller tasks, the assistant can use only `flow_find` and `flow_lookup`.
For refactors or architecture work, it should usually combine `flow_map`, `flow_impact`,
and `flow_risk`.

## Notes

- Core MCP tools work without an LLM provider key.
- The MCP server reads local project files through Flow Engine.
- Keep machine-specific paths in local MCP client settings, not in committed files.
