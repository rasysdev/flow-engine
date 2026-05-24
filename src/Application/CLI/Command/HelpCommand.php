<?php

namespace FlowEngine\Application\CLI\Command;

use FlowEngine\Console\ConsoleIO;

final class HelpCommand implements Command
{
    public function __construct(
        private ConsoleIO $io
    ) {
    }

    public function supports(string $command): bool
    {
        return $command === 'help' || $command === '--help' || $command === '-h';
    }

    public function handle(array $argv): void
    {
        $specific = $argv[2] ?? null;

        if ($specific !== null) {
            $this->showCommandHelp($specific);
            return;
        }

        $this->showGeneralHelp();
    }

    private function showGeneralHelp(): void
    {
        echo <<<'HELP'
Flow Engine — Static Analysis & Architecture Intelligence CLI

Usage: php bin/engine.php <command> [options]

SETUP & DIAGNOSTICS
  init <path>                    Create flow-engine.json config file
  doctor <path>                  Validate project configuration
  analyze <path>                 Analyze project and build dependency graph

ANALYSIS COMMANDS
  metrics <path> [--context]                      Show project metrics (JSON or markdown)
  complexity <path> [--context]                   Analyze code complexity
  cycles <path> [--context]                       Detect dependency cycles
  architecture <path> [--context]                 Detect architecture violations
  orphans <path> [--context] [--audit]            Find orphan nodes (unreferenced code)
  solid <path> [--format=text|json] [--principle] Detect SOLID violations
  patterns <path> [--format=text|json] [--pattern] Detect design patterns
  bugs <path> [--min-score=N] [--type=TYPE]       Static bug detection
  entrypoints <path> [--mode=actionable|raw]      List entry points (controllers, CLI, etc.)

NODE INSPECTION
  nodes <path>                                    List all analyzed nodes
  flow <path>                                     Export full flow graph (nodes + edges)
  inputs <path> <Class::method>                   Show node inputs and return type
  impact <path> <Class::method>                   Analyze change impact for a node
  impact-report <path> --node=<id> [--interpret]  Detailed impact report
  change-risk <path> --node=<Class::method>       Calculate change risk score
  trace <path> --node=<id> [--direction=both]     Trace upstream/downstream dependencies
  explain <path> <Class::method>                  Explain governance decisions

AI-ASSISTED ANALYSIS
  ask "<question>" <path> [--minimal]             Ask questions about the codebase
  interpret <path> --type=<type> [--node=<id>]    AI interpretation (cycles|hotspots|impact|violations)
  context <path> [options]                        Export context for AI assistants
    --minimal                                     Compact output (metrics only)
    --sections=<s1,s2,...>                        Select specific sections
    --focus=<namespace>                           Filter by namespace
    --entrypoint=<Class::method>                  Focus on specific entry point
    --depth=<N>                                   Subgraph depth (default: 5)
    --catalog=<path>                              Multi-service catalog file

REFACTORING WORKFLOW
  refactor-plan <path> --node=<id> [--format=json|markdown] [--save=<label>]
                                                  Generate refactoring plan
  refactor-safety <path> --node=<Class::method>   Assess refactoring safety
  refactor-execute <path> --plan=<label> --step=<N> [--mark-done]
                                                  Get guidance for refactor step
  refactor-validate <path> --plan=<label> --step=<N>
                                                  Validate refactor step completion
  refactor-pr <path> --plan=<label> [--publish]   Generate PR from refactor plan

SIMULATION & GATE
  simulate <path> [--scope=hotspots:<N>|namespace:<Ns>|all] [--nodes=<ids>]
                                                  Simulate large-scale refactor
  architecture-gate <path> [--baseline=<label>] [--fail-on=any|new|critical]
                                                  CI architecture gate (exit 0/1)

DIAGRAMS & VISUALIZATION
  diagram <path> [--view=flowchart|class|component|c4context] [--entrypoint=<id>]
                                                  Generate Mermaid diagrams
  diagram <path_a> <path_b> [--type=dependency|sequence|c4container]
                                                  Multi-project diagram

APPLICATION MAP (Multi-Service)
  appmap <path_a> <path_b> [...]                  Build application map
  appmap --catalog=<flow-services.json>           Build from catalog
  appmap-diff <before.json> <after.json> [--policy=<path>]
                                                  Compare application maps

SNAPSHOTS
  snapshot <path> --save=<label>                  Save current state
  snapshot <path> --compare=<label>               Compare with baseline
  snapshot <path> --list                          List saved snapshots
  drift <path> --baseline=<label> [--policy=<path>]
                                                  Detect drift from baseline
  cleanup <path> [--older-than=<days>] [--keep-last=<N>] [--stats]
                                                  Clean old snapshots

REMEDIATION
  remediation-proposals <path> [--max=<N>] [--format=json|markdown] [--save=<label>]
                                                  Generate remediation proposals
  remediation-approve <path> --plan=<label> --id=<proposal_id> [--by=<name>]
                                                  Approve a proposal
  remediation-status <path> --plan=<label>        Check approval status

SERVERS & INTEGRATIONS
  api <path> [--host=127.0.0.1] [--port=8080]     Start REST API server
  mcp                                             Start MCP server (stdio)
  watch <path> [analysis] [--interval=<N>] [--watcher=auto|polling|native]
                                                  Watch for changes

UTILITIES
  compare <path_a> <path_b>                       Compare two projects
  interactive [path]                              Interactive mode

Use "php bin/engine.php help <command>" for detailed help on a specific command.

HELP;
    }

    private function showCommandHelp(string $command): void
    {
        $help = $this->getCommandHelp($command);

        if ($help === null) {
            $this->io->error("Unknown command: {$command}");
            $this->io->info("Run 'php bin/engine.php help' for a list of commands.");
            return;
        }

        echo $help;
    }

    private function getCommandHelp(string $command): ?string
    {
        $commands = [
            'init' => <<<'HELP'
flow init <project_path>

Create a flow-engine.json configuration file for the project.

Arguments:
  <project_path>    Path to the project (default: current directory)

Description:
  Generates a default configuration file based on the project structure:
  - For PHP/Composer projects: scans src/ or app/ directories
  - For Flutter/Dart projects: scans lib/, test/, integration_test/

Example:
  php bin/engine.php init .
  php bin/engine.php init /path/to/project

HELP,

            'doctor' => <<<'HELP'
flow doctor <project_path>

Validate project configuration and environment.

Arguments:
  <project_path>    Path to the project (default: current directory)

Checks performed:
  - flow-engine.json exists and is valid JSON
  - Autoload file exists (if configured)
  - Include directories exist
  - Exclude directories exist
  - PHP version compatibility

Example:
  php bin/engine.php doctor .

HELP,

            'analyze' => <<<'HELP'
flow analyze <project_path>

Analyze the project and build the dependency graph.

Arguments:
  <project_path>    Path to the project

Description:
  Scans all files in the configured directories, parses them, and builds
  a graph of nodes (classes, functions, methods) and edges (dependencies).
  This is required before running most other commands.

Example:
  php bin/engine.php analyze .

HELP,

            'metrics' => <<<'HELP'
flow metrics <project_path> [--context]

Show project metrics.

Arguments:
  <project_path>    Path to the project

Options:
  --context         Output as markdown (for AI context)

Metrics included:
  - Total nodes and edges
  - Average/max fan-in and fan-out
  - Hotspot count
  - Top hotspots list

Example:
  php bin/engine.php metrics .
  php bin/engine.php metrics . --context

HELP,

            'complexity' => <<<'HELP'
flow complexity <project_path> [--context]

Analyze code complexity.

Arguments:
  <project_path>    Path to the project

Options:
  --context         Output as markdown (for AI context)

Analysis includes:
  - Cyclomatic complexity per node
  - High-complexity hotspots
  - Complexity distribution

Example:
  php bin/engine.php complexity .

HELP,

            'cycles' => <<<'HELP'
flow cycles <project_path> [--context]

Detect dependency cycles in the codebase.

Arguments:
  <project_path>    Path to the project

Options:
  --context         Output as markdown (for AI context)

Description:
  Uses Tarjan's algorithm to find strongly connected components (SCCs).
  Each SCC with more than one node represents a dependency cycle.

Example:
  php bin/engine.php cycles .

HELP,

            'architecture' => <<<'HELP'
flow architecture <project_path> [--context]

Detect architecture violations.

Arguments:
  <project_path>    Path to the project

Options:
  --context         Output as markdown (for AI context)

Detects violations of:
  - Clean Architecture layer boundaries
  - Domain isolation rules
  - Infrastructure dependencies

Example:
  php bin/engine.php architecture .

HELP,

            'orphans' => <<<'HELP'
flow orphans <project_path> [--context] [--audit]

Find orphan nodes (unreferenced code).

Arguments:
  <project_path>    Path to the project

Options:
  --context         Output as markdown (for AI context)
  --audit           Add audit information (roots, summary)

Description:
  Identifies nodes that have no incoming edges (not called by anyone).
  Useful for finding dead code or unused methods.

Example:
  php bin/engine.php orphans .
  php bin/engine.php orphans . --audit

HELP,

            'solid' => <<<'HELP'
flow solid <project_path> [--format=text|json] [--principle=srp|isp|dip|ocp|all]

Detect SOLID principle violations.

Arguments:
  <project_path>    Path to the project

Options:
  --format=<fmt>        Output format: text (default) or json
  --principle=<p>       Filter by principle: srp, isp, dip, ocp, all (default)

Principles analyzed:
  - SRP: Single Responsibility (high fan-out classes)
  - ISP: Interface Segregation (large interfaces)
  - DIP: Dependency Inversion (direct instantiation of concrete classes)
  - OCP: Open/Closed (opportunities for abstraction)

Example:
  php bin/engine.php solid .
  php bin/engine.php solid . --format=json
  php bin/engine.php solid . --principle=dip

HELP,

            'patterns' => <<<'HELP'
flow patterns <project_path> [--format=text|json] [--pattern=<name>|all]

Detect design patterns in the codebase.

Arguments:
  <project_path>    Path to the project

Options:
  --format=<fmt>    Output format: text (default) or json
  --pattern=<name>  Filter: repository, factory, strategy, observer, command, adapter, all

Patterns detected:
  - Repository Pattern
  - Factory Pattern
  - Strategy Pattern
  - Observer Pattern
  - Command Pattern
  - Adapter Pattern

Example:
  php bin/engine.php patterns .
  php bin/engine.php patterns . --pattern=repository

HELP,

            'bugs' => <<<'HELP'
flow bugs <project_path> [--min-score=N] [--type=TYPE] [--format=text|json]

Static bug detection.

Arguments:
  <project_path>    Path to the project

Options:
  --min-score=<N>   Minimum bug score to report (default: 0)
  --type=<TYPE>     Filter by bug type
  --format=<fmt>    Output format: text (default) or json

Example:
  php bin/engine.php bugs .
  php bin/engine.php bugs . --min-score=50

HELP,

            'entrypoints' => <<<'HELP'
flow entrypoints <project_path> [--mode=actionable|raw]

List entry points in the codebase.

Arguments:
  <project_path>    Path to the project

Options:
  --mode=<mode>     actionable (default): filter out constructors/magic methods
                    raw: show all graph roots

Entry point types detected:
  - HTTP (controllers, actions)
  - CLI (console commands)
  - Event (listeners, handlers)
  - Livewire (components)
  - Eloquent (accessors/mutators)

Example:
  php bin/engine.php entrypoints .
  php bin/engine.php entrypoints . --mode=raw

HELP,

            'context' => <<<'HELP'
flow context <project_path> [options]

Export project context for AI assistants.

Arguments:
  <project_path>    Path to the project

Options:
  --minimal                    Compact output (metrics only)
  --sections=<s1,s2,...>       Select sections: metrics, complexity, cycles, architecture, orphans, serviceMap, dataModel, routes
  --focus=<namespace>          Filter by namespace
  --entrypoint=<Class::method> Focus on specific entry point subgraph
  --depth=<N>                  Subgraph depth (default: 5)
  --strict-entrypoint          Only include entrypoint subgraph
  --catalog=<path>             Multi-service catalog for service map

Example:
  php bin/engine.php context . --minimal
  php bin/engine.php context . --entrypoint=OrderController::store --depth=3
  php bin/engine.php context . --sections=metrics,cycles

HELP,

            'refactor-plan' => <<<'HELP'
flow refactor-plan <project_path> --node=<Class::method> [--format=json|markdown] [--save=<label>]

Generate a refactoring plan for a specific node.

Arguments:
  <project_path>    Path to the project

Options:
  --node=<id>       Target node (required)
  --format=<fmt>    Output format: json (default) or markdown
  --save=<label>    Save plan with label for later execution

Example:
  php bin/engine.php refactor-plan . --node=UserService::create
  php bin/engine.php refactor-plan . --node=UserService::create --save=user-refactor --format=markdown

HELP,

            'refactor-safety' => <<<'HELP'
flow refactor-safety <project_path> --node=<Class::method>

Assess the safety of refactoring a specific node.

Arguments:
  <project_path>    Path to the project

Options:
  --node=<id>       Target node (required)

Returns:
  - Safety score
  - Risk factors
  - Affected dependencies

Example:
  php bin/engine.php refactor-safety . --node=UserService::create

HELP,

            'refactor-execute' => <<<'HELP'
flow refactor-execute <project_path> --plan=<label> --step=<N> [--format=json|markdown] [--mark-done]

Get guidance for executing a refactor step.

Arguments:
  <project_path>    Path to the project

Options:
  --plan=<label>    Plan label (required)
  --step=<N>        Step number (required, 1-based)
  --format=<fmt>    Output format: json (default) or markdown
  --mark-done       Mark step as completed

Example:
  php bin/engine.php refactor-execute . --plan=user-refactor --step=1
  php bin/engine.php refactor-execute . --plan=user-refactor --step=1 --mark-done

HELP,

            'refactor-validate' => <<<'HELP'
flow refactor-validate <project_path> --plan=<label> --step=<N>

Validate that a refactor step was completed correctly.

Arguments:
  <project_path>    Path to the project

Options:
  --plan=<label>    Plan label (required)
  --step=<N>        Step number (required, 1-based)

Exit codes:
  0 - Validation passed
  1 - Validation failed

Example:
  php bin/engine.php refactor-validate . --plan=user-refactor --step=1

HELP,

            'refactor-pr' => <<<'HELP'
flow refactor-pr <project_path> --plan=<label> [--format=json|markdown] [--publish]

Generate a pull request from a completed refactor plan.

Arguments:
  <project_path>    Path to the project

Options:
  --plan=<label>    Plan label (required)
  --format=<fmt>    Output format: json or markdown (default)
  --publish         Create PR via GitHub CLI (requires gh)

Example:
  php bin/engine.php refactor-pr . --plan=user-refactor
  php bin/engine.php refactor-pr . --plan=user-refactor --publish

HELP,

            'simulate' => <<<'HELP'
flow simulate <project_path> [--scope=<scope>] [--nodes=<ids>] [--format=json|markdown]

Simulate a large-scale refactoring.

Arguments:
  <project_path>    Path to the project

Options:
  --scope=<scope>   hotspots:<N> (default: hotspots:20)
                    namespace:<Namespace>
                    all
  --nodes=<ids>     Comma-separated list of node IDs (overrides scope)
  --format=<fmt>    Output format: json (default) or markdown

Example:
  php bin/engine.php simulate . --scope=hotspots:10
  php bin/engine.php simulate . --nodes=UserService::create,OrderService::place

HELP,

            'architecture-gate' => <<<'HELP'
flow architecture-gate <project_path> [--baseline=<label>] [--fail-on=any|new|critical] [--format=json|markdown]

CI-friendly architecture gate.

Arguments:
  <project_path>    Path to the project

Options:
  --baseline=<label>    Compare with saved snapshot (for incremental enforcement)
  --fail-on=<mode>      any (default): fail on any violation
                        new: fail only on new violations since baseline
                        critical: fail only on CRITICAL severity
  --format=<fmt>        Output format: json (default) or markdown

Exit codes:
  0 - Gate passed
  1 - Gate failed

Example:
  php bin/engine.php architecture-gate .
  php bin/engine.php architecture-gate . --baseline=before-pr --fail-on=new

HELP,

            'diagram' => <<<'HELP'
flow diagram <project_path> [--view=<type>] [--entrypoint=<id>] [--namespace=<ns>] [--depth=<N>]

Generate Mermaid diagrams.

Single project mode:
  --view=flowchart      Entry point flowchart (requires --entrypoint)
  --view=class          Class diagram
  --view=component      Component diagram
  --view=c4context      C4 context diagram
  --entrypoint=<id>     Required for flowchart view
  --namespace=<ns>      Filter by namespace (class/component views)
  --depth=<N>           Flowchart depth (default: 5)

Multi-project mode:
  flow diagram <path_a> <path_b> [--type=<type>]
  --type=dependency     Dependency graph (default)
  --type=sequence       Sequence diagram
  --type=c4container    C4 container diagram

Catalog mode:
  flow diagram --catalog=flow-services.json [--type=<type>]

Example:
  php bin/engine.php diagram . --view=flowchart --entrypoint=OrderController::store
  php bin/engine.php diagram . --view=class --namespace=App\\Domain
  php bin/engine.php diagram ./service-a ./service-b --type=sequence

HELP,

            'appmap' => <<<'HELP'
flow appmap <path_a> <path_b> [...]
flow appmap --catalog=flow-services.json

Build an application map from multiple services.

Arguments:
  <path_a> <path_b>    Two or more project paths

Options:
  --catalog=<path>     Load services from flow-services.json catalog

Example:
  php bin/engine.php appmap ./api ./frontend ./worker
  php bin/engine.php appmap --catalog=flow-services.json

HELP,

            'appmap-diff' => <<<'HELP'
flow appmap-diff <before.json> <after.json> [--policy=<path>]

Compare two application maps.

Arguments:
  <before.json>     Path to baseline appmap JSON
  <after.json>      Path to current appmap JSON

Options:
  --policy=<path>   Drift policy JSON for gate evaluation

Exit codes:
  0 - No violations or policy passed
  1 - Policy gate failed

Example:
  php bin/engine.php appmap-diff baseline.json current.json
  php bin/engine.php appmap-diff baseline.json current.json --policy=drift-policy.json

HELP,

            'snapshot' => <<<'HELP'
flow snapshot <project_path> --save=<label>
flow snapshot <project_path> --compare=<label>
flow snapshot <project_path> --list

Manage analysis snapshots.

Arguments:
  <project_path>    Path to the project

Options:
  --save=<label>      Save current state with label
  --compare=<label>   Compare current state with saved snapshot
  --list              List all saved snapshots

Example:
  php bin/engine.php snapshot . --save=before-refactor
  php bin/engine.php snapshot . --compare=before-refactor
  php bin/engine.php snapshot . --list

HELP,

            'drift' => <<<'HELP'
flow drift <project_path> --baseline=<label> [--policy=<path>]

Detect drift from a baseline snapshot.

Arguments:
  <project_path>    Path to the project

Options:
  --baseline=<label>   Baseline snapshot label (required)
  --policy=<path>      Drift policy JSON for evaluation

Example:
  php bin/engine.php drift . --baseline=pre-refactor
  php bin/engine.php drift . --baseline=pre-refactor --policy=drift-policy.json

HELP,

            'cleanup' => <<<'HELP'
flow cleanup <project_path> [--older-than=<days>] [--keep-last=<N>] [--stats]

Clean up old snapshots.

Arguments:
  <project_path>    Path to the project

Options:
  --older-than=<days>   Delete snapshots older than N days
  --keep-last=<N>       Keep only the last N snapshots
  --stats               Show storage statistics

Example:
  php bin/engine.php cleanup . --older-than=30
  php bin/engine.php cleanup . --keep-last=10
  php bin/engine.php cleanup . --stats

HELP,

            'api' => <<<'HELP'
flow api <project_path> [--host=127.0.0.1] [--port=8080]

Start the REST API server.

Arguments:
  <project_path>    Path to the project

Options:
  --host=<host>     Bind address (default: 127.0.0.1)
  --port=<port>     Listen port (default: 8080)

Available endpoints:
  GET /health
  GET /api/v1/metrics
  GET /api/v1/cycles
  GET /api/v1/architecture
  GET /api/v1/orphans
  GET /api/v1/nodes
  GET /api/v1/edges
  GET /api/v1/flow
  GET /api/v1/snapshots
  GET /api/v1/appmap
  GET /api/v1/diagram
  ... and more

Example:
  php bin/engine.php api .
  php bin/engine.php api . --host=0.0.0.0 --port=3000

HELP,

            'mcp' => <<<'HELP'
flow mcp

Start the MCP (Model Context Protocol) server.

Description:
  Runs an MCP server over stdio transport for integration with
  AI assistants like Claude Code.

Available tools:
  - analyze_project
  - get_metrics
  - get_cycles
  - get_architecture
  - get_orphans
  - context_export
  - impact_report
  - refactor_plan
  ... and more

Example:
  php bin/engine.php mcp

HELP,

            'watch' => <<<'HELP'
flow watch <project_path> [analysis] [--interval=<N>] [--watcher=auto|polling|native]

Watch for file changes and re-run analysis.

Arguments:
  <project_path>    Path to the project
  [analysis]        Analysis to run: metrics (default), complexity, cycles, architecture, orphans

Options:
  --interval=<N>        Check interval in seconds (default: 2)
  --watcher=<mode>      auto (default), polling, or native

Example:
  php bin/engine.php watch . metrics
  php bin/engine.php watch . complexity --interval=5

HELP,

            'ask' => <<<'HELP'
flow ask "<question>" <project_path> [--minimal]

Ask questions about the codebase using AI.

Arguments:
  "<question>"      Question to ask (quoted)
  <project_path>    Path to the project

Options:
  --minimal         Use minimal context (faster, cheaper)

Example:
  php bin/engine.php ask "What are the main entry points?" .
  php bin/engine.php ask "How does authentication work?" . --minimal

HELP,

            'interpret' => <<<'HELP'
flow interpret <project_path> --type=<type> [--node=<id>]

Get AI interpretation of analysis results.

Arguments:
  <project_path>    Path to the project

Options:
  --type=<type>     cycles, hotspots, impact, violations (required)
  --node=<id>       Node ID (required for impact type)

Example:
  php bin/engine.php interpret . --type=cycles
  php bin/engine.php interpret . --type=impact --node=UserService::create

HELP,

            'trace' => <<<'HELP'
flow trace <project_path> --node=<id> [--direction=downstream|upstream|both]

Trace dependencies for a node.

Arguments:
  <project_path>    Path to the project

Options:
  --node=<id>            Node ID (required)
  --direction=<dir>      downstream, upstream, or both (default)

Example:
  php bin/engine.php trace . --node=UserService::create
  php bin/engine.php trace . --node=UserService::create --direction=upstream

HELP,

            'impact-report' => <<<'HELP'
flow impact-report <project_path> --node=<id> [--interpret]

Generate a detailed impact report for a node.

Arguments:
  <project_path>    Path to the project

Options:
  --node=<id>       Node ID (required)
  --interpret       Include AI interpretation

Example:
  php bin/engine.php impact-report . --node=UserService::create
  php bin/engine.php impact-report . --node=UserService::create --interpret

HELP,

            'change-risk' => <<<'HELP'
flow change-risk <project_path> --node=<id>

Calculate change risk score for a node.

Arguments:
  <project_path>    Path to the project

Options:
  --node=<id>       Node ID (required)

Returns:
  - Risk score (0-100)
  - Risk factors
  - Recommendations

Example:
  php bin/engine.php change-risk . --node=UserService::create

HELP,

            'remediation-proposals' => <<<'HELP'
flow remediation-proposals <project_path> [--max=<N>] [--format=json|markdown] [--save=<label>]

Generate remediation proposals for code issues.

Arguments:
  <project_path>    Path to the project

Options:
  --max=<N>           Maximum proposals (default: 10)
  --format=<fmt>      json (default) or markdown
  --save=<label>      Save proposals with label

Example:
  php bin/engine.php remediation-proposals .
  php bin/engine.php remediation-proposals . --max=5 --save=sprint-1

HELP,

            'remediation-approve' => <<<'HELP'
flow remediation-approve <project_path> --plan=<label> --id=<proposal_id> [--by=<name>] [--format=json|markdown]

Approve a remediation proposal.

Arguments:
  <project_path>    Path to the project

Options:
  --plan=<label>    Plan label (required)
  --id=<id>         Proposal ID (required)
  --by=<name>       Approver name
  --format=<fmt>    json (default) or markdown

Example:
  php bin/engine.php remediation-approve . --plan=sprint-1 --id=prop-001 --by="John Doe"

HELP,

            'remediation-status' => <<<'HELP'
flow remediation-status <project_path> --plan=<label> [--format=json|markdown]

Check status of remediation proposals.

Arguments:
  <project_path>    Path to the project

Options:
  --plan=<label>    Plan label (required)
  --format=<fmt>    json (default) or markdown

Example:
  php bin/engine.php remediation-status . --plan=sprint-1

HELP,

            'compare' => <<<'HELP'
flow compare <project_path_a> <project_path_b>

Compare two projects.

Arguments:
  <project_path_a>    First project path
  <project_path_b>    Second project path

Returns:
  - Flow diff (added/removed nodes and edges)
  - Report diffs (metrics, complexity, cycles, etc.)

Example:
  php bin/engine.php compare ./v1 ./v2

HELP,

            'nodes' => <<<'HELP'
flow nodes <project_path>

List all analyzed nodes.

Arguments:
  <project_path>    Path to the project

Returns JSON array with all node IDs.

Example:
  php bin/engine.php nodes .

HELP,

            'flow' => <<<'HELP'
flow flow <project_path>

Export the full dependency graph.

Arguments:
  <project_path>    Path to the project

Returns:
  - nodes: Array of node objects
  - edges: Array of edge objects
  - nodeCount: Total nodes
  - edgeCount: Total edges

Example:
  php bin/engine.php flow .

HELP,

            'inputs' => <<<'HELP'
flow inputs <project_path> <Class::method>

Show inputs and return type for a node.

Arguments:
  <project_path>    Path to the project
  <Class::method>   Node ID

Example:
  php bin/engine.php inputs . UserService::create

HELP,

            'impact' => <<<'HELP'
flow impact <project_path> <Class::method>

Analyze change impact for a node.

Arguments:
  <project_path>    Path to the project
  <Class::method>   Node ID

Example:
  php bin/engine.php impact . UserService::create

HELP,

            'explain' => <<<'HELP'
flow explain <project_path> <Class::method>

Explain governance decisions for a node.

Arguments:
  <project_path>    Path to the project
  <Class::method>   Node ID

Shows how policies were evaluated and the final decision.

Example:
  php bin/engine.php explain . UserService::create

HELP,

            'interactive' => <<<'HELP'
flow interactive [project_path]

Start interactive mode.

Arguments:
  [project_path]    Path to the project (will prompt if not provided)

Provides a menu-driven interface for common commands.

Example:
  php bin/engine.php interactive
  php bin/engine.php interactive .

HELP,
        ];

        return $commands[$command] ?? null;
    }
}
