<?php

namespace FlowEngine\Application\CLI\Command;

use FlowEngine\Bootstrap\Container;
use FlowEngine\Console\ConsoleIO;
use LogicException;

final class RunGuidedCommand implements Command
{
    public function __construct(
        private ConsoleIO $io
    ) {
    }

    public function supports(string $command): bool
    {
        return $command === 'run-guided';
    }

    public function handle(array $argv): void
    {
        [$projectPath, $nodeId] = [$argv[2] ?? null, $argv[3] ?? null];

        if (!$projectPath || !$nodeId) {
            $this->io->error(
                'Usage: run-guided <project_path> <Class::method> [args...]'
            );
            return;
        }

        $rawArgs = array_slice($argv, 4);

        $container = new Container($projectPath);
        $container->analyzeProject()->execute();

        try {
            /* =========================
               IA (somente leitura)
               ========================= */
            $assistant = $container->guidedAssistant();

            $suggestionsResult = $assistant->suggestInputs(
                $container->buildGuidedInputContext($nodeId)
            );

            if (!$suggestionsResult->isEmpty()) {
                $this->io->json([
                    'suggestions' => array_map(
                        fn($s) => [
                            'name' => $s->name,
                            'type' => $s->type,
                            'suggestedValue' => $s->suggestedValue,
                            'reason' => $s->reason,
                        ],
                        $suggestionsResult->suggestions()
                    )
                ]);
            }

            /* =========================
               Execução real (imutável)
               ========================= */
            $result = $container
                ->runNodeGuided()
                ->execute($nodeId, $rawArgs);

            $this->io->json([
                'node' => $result->nodeId,
                'inputs' => array_map(
                    fn($input) => [
                        'name' => $input->name,
                        'type' => $input->type,
                        'value' => $input->value,
                    ],
                    $result->inputs
                ),
                'result' => [
                    'output' => $result->result->output,
                ],
            ]);
        } catch (LogicException $e) {
            $this->io->error($e->getMessage());
        }
    }
}
