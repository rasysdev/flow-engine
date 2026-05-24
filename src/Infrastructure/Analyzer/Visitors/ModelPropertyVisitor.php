<?php

namespace FlowEngine\Infrastructure\Analyzer\Visitors;

use FlowEngine\Domain\Flow\Edge;
use PhpParser\Node;

/**
 * Detects Eloquent Model properties and relationships.
 *
 * Captures:
 * - protected $fillable = [...] -> field list
 * - protected $casts = [...] -> field type map
 * - protected $table = '...' -> table name
 * - $this->hasMany(Comment::class) etc -> relationships + edges
 *
 * On class leave, creates a synthetic node ClassName::__model with
 * metadata containing the full schema.
 *
 * @internal
 */
final class ModelPropertyVisitor implements NodeVisitor
{
    private array $fillable = [];
    private array $casts = [];
    private ?string $table = null;
    /** @var array<int, array{type: string, related: string}> */
    private array $relationships = [];
    private bool $isModel = false;
    private ?string $currentModelClass = null;

    private const RELATIONSHIP_METHODS = [
        'hasMany', 'hasOne', 'belongsTo', 'belongsToMany',
        'hasManyThrough', 'hasOneThrough', 'morphTo',
        'morphMany', 'morphOne', 'morphToMany', 'morphedByMany',
    ];

    /**
     * @internal
     */
    public function supports(Node $node): bool
    {
        return $node instanceof Node\Stmt\Class_
            || $node instanceof Node\Stmt\Property
            || $node instanceof Node\Expr\MethodCall;
    }

    /**
     * @internal
     */
    public function enter(Node $node, VisitorContext $context): void
    {
        if ($node instanceof Node\Stmt\Class_) {
            $this->reset();
            $this->isModel = $this->extendsModel($node);
            if ($this->isModel) {
                $this->currentModelClass = $context->currentClass();
            }
            return;
        }

        if (!$this->isModel) {
            return;
        }

        if ($node instanceof Node\Stmt\Property) {
            $this->processProperty($node, $context);
            return;
        }

        if ($node instanceof Node\Expr\MethodCall) {
            $this->processMethodCall($node, $context);
        }
    }

    /**
     * @internal
     */
    public function leave(Node $node, VisitorContext $context): void
    {
        if (!$node instanceof Node\Stmt\Class_) {
            return;
        }

        if (!$this->isModel) {
            return;
        }

        $class = $this->currentModelClass;
        if ($class === null) {
            return;
        }

        // Only create __model node if we found something
        if ($this->fillable === [] && $this->casts === [] && $this->table === null && $this->relationships === []) {
            return;
        }

        $metadata = [];
        if ($this->table !== null) {
            $metadata['table'] = $this->table;
        }
        if ($this->fillable !== []) {
            $metadata['fillable'] = $this->fillable;
        }
        if ($this->casts !== []) {
            $metadata['casts'] = $this->casts;
        }
        if ($this->relationships !== []) {
            $metadata['relationships'] = $this->relationships;
        }

        $modelNode = $context->nodeFactory()->create(
            $class,
            '__model',
            $context->file(),
            $node->getStartLine(),
            'php',
            $metadata
        );
        $context->addNode($modelNode);

        // Create relationship edges
        foreach ($this->relationships as $rel) {
            $related = $rel['related'];
            $context->addEdge(new Edge(
                $class . '::__model',
                $related . '::__model',
                $rel['type'],
                'model_relationship'
            ));
        }

        $this->reset();
    }

    private function reset(): void
    {
        $this->fillable = [];
        $this->casts = [];
        $this->table = null;
        $this->relationships = [];
        $this->isModel = false;
        $this->currentModelClass = null;
    }

    private function extendsModel(Node\Stmt\Class_ $node): bool
    {
        if ($node->extends === null) {
            return false;
        }

        $parent = $node->extends->toString();

        return in_array($parent, [
            'Model',
            'Illuminate\\Database\\Eloquent\\Model',
            'Authenticatable',
            'Illuminate\\Foundation\\Auth\\User',
        ], true) || str_ends_with($parent, '\\Model');
    }

    private function processProperty(Node\Stmt\Property $node, VisitorContext $context): void
    {
        if (count($node->props) !== 1) {
            return;
        }

        $prop = $node->props[0];
        $name = $prop->name->toString();
        $default = $prop->default;

        if ($name === 'fillable' && $default instanceof Node\Expr\Array_) {
            $this->fillable = $this->extractArrayStrings($default);
            return;
        }

        if ($name === 'casts' && $default instanceof Node\Expr\Array_) {
            $this->casts = $this->extractArrayMap($default);
            return;
        }

        if ($name === 'table' && $default instanceof Node\Scalar\String_) {
            $this->table = $default->value;
        }
    }

    private function processMethodCall(Node\Expr\MethodCall $node, VisitorContext $context): void
    {
        // Must be $this->hasMany(...) etc.
        if (!$node->var instanceof Node\Expr\Variable
            || $node->var->name !== 'this'
            || !$node->name instanceof Node\Identifier) {
            return;
        }

        $method = $node->name->toString();
        if (!in_array($method, self::RELATIONSHIP_METHODS, true)) {
            return;
        }

        // First argument should be SomeModel::class
        if (count($node->args) < 1 || !$node->args[0] instanceof Node\Arg) {
            return;
        }

        $arg = $node->args[0]->value;
        if ($arg instanceof Node\Expr\ClassConstFetch
            && $arg->class instanceof Node\Name
            && $arg->name instanceof Node\Identifier
            && $arg->name->toString() === 'class'
        ) {
            $related = $context->resolveFQN($arg->class->toString());
            $this->relationships[] = [
                'type' => $method,
                'related' => $related,
            ];
        }
    }

    /**
     * @return string[]
     */
    private function extractArrayStrings(Node\Expr\Array_ $array): array
    {
        $values = [];
        foreach ($array->items as $item) {
            if ($item !== null && $item->value instanceof Node\Scalar\String_) {
                $values[] = $item->value->value;
            }
        }
        return $values;
    }

    /**
     * @return array<string, string>
     */
    private function extractArrayMap(Node\Expr\Array_ $array): array
    {
        $map = [];
        foreach ($array->items as $item) {
            if ($item === null) {
                continue;
            }
            if ($item->key instanceof Node\Scalar\String_ && $item->value instanceof Node\Scalar\String_) {
                $map[$item->key->value] = $item->value->value;
            }
        }
        return $map;
    }
}
