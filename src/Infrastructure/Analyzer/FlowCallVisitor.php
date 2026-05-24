<?php

namespace FlowEngine\Infrastructure\Analyzer;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

final class FlowCallVisitor extends NodeVisitorAbstract
{
    private ?string $currentClass = null;

    /** @var array<string, string> */
    private array $variables = [];

    /** @var array<int, array> */
    private array $edges = [];

    public function enterNode(Node $node): int|Node|null
    {
        if ($node instanceof Node\Stmt\Class_) {
            $this->currentClass = $node->name?->toString();
            $this->variables = [];
        }

        if (
            $node instanceof Node\Expr\Assign &&
            $node->expr instanceof Node\Expr\New_ &&
            $node->expr->class instanceof Node\Name &&
            $node->var instanceof Node\Expr\Variable
        ) {
            $this->variables[$node->var->name] =
                $node->expr->class->toString();
        }

        if (
            $node instanceof Node\Expr\MethodCall &&
            $node->var instanceof Node\Expr\Variable &&
            isset($this->variables[$node->var->name]) &&
            $node->name instanceof Node\Identifier
        ) {
            $this->edges[] = [
                'from' => $this->currentClass,
                'to' => $this->variables[$node->var->name],
                'method' => $node->name->toString(),
            ];
        }
    }

    /** @return array<int, array> */
    public function edges(): array
    {
        return $this->edges;
    }
}
