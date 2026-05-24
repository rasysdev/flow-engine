<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\Application\DTO\GuidedArgumentsResult;
use FlowEngine\Application\DTO\ResolvedInput;
use FlowEngine\Application\DTO\NodeInputDefinition;
use FlowEngine\Domain\Flow\Node;
use LogicException;

final class ResolveGuidedArguments
{
    /**
     * @param NodeInputDefinition[] $inputs
     * @param array<int, mixed> $rawArgs
     */
    public function execute(
        Node $node,
        array $inputs,
        array $rawArgs
    ): GuidedArgumentsResult {
        $finalArgs = [];
        $resolvedInputs = [];

        foreach ($inputs as $i => $input) {
            $value = $rawArgs[$i] ?? $input->default;

            if ($input->required && $value === null) {
                throw new LogicException(
                    "Missing argument: {$input->name} for {$node->id()}"
                );
            }

            $casted = $this->cast($value, $input->type);

            $finalArgs[] = $casted;
            $resolvedInputs[] = new ResolvedInput(
                name: $input->name,
                type: $input->type,
                value: $casted
            );
        }

        return new GuidedArgumentsResult(
            args: $finalArgs,
            inputs: $resolvedInputs
        );
    }

    private function cast(mixed $value, string $type): mixed
    {
        return match ($type) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'string' => (string) $value,
            default => $value,
        };
    }
}
