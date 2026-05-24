<?php

namespace FlowEngine\Application\CLI\Command;

use FlowEngine\AI\Export\MarkdownFormatter;
use FlowEngine\Application\Service\OrphanAuditService;
use FlowEngine\Bootstrap\Container;
use FlowEngine\Console\ConsoleIO;

final class OrphansCommand implements Command
{
    public function __construct(
        private ConsoleIO $io
    ) {
    }

    public function supports(string $command): bool
    {
        return $command === 'orphans';
    }

    public function handle(array $argv): void
    {
        $projectPath = $argv[2] ?? null;
        $withAudit = in_array('--audit', $argv, true);
        $withContext = in_array('--context', $argv, true);

        if (!$projectPath) {
            $this->io->error('Usage: flow orphans <project_path> [--context] [--audit]');
            return;
        }

        if ($withAudit && $withContext) {
            $this->io->error('Cannot combine --context and --audit in the same invocation.');
            return;
        }

        $container = new Container($projectPath);
        $container->analyzeProject()->execute();

        $result = $container->analyzeOrphans()->execute();

        if ($withContext) {
            echo (new MarkdownFormatter())->formatOrphans($result);
            return;
        }

        $payload = $result->toArray();

        if ($withAudit) {
            $audit = (new OrphanAuditService($projectPath))->audit($payload['orphans'] ?? []);
            $payload['orphans'] = $audit['orphans'];
            $payload['auditSummary'] = $audit['summary'];
            $payload['auditRoots'] = $audit['roots'];
        }

        $this->io->json($payload);
    }
}
