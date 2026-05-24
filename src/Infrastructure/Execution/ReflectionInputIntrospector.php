<?php

namespace FlowEngine\Infrastructure\Execution;

use FlowEngine\Domain\Contracts\InputIntrospector;
use FlowEngine\Domain\Contracts\ProjectContext;
use FlowEngine\Domain\Flow\Node;

final class ReflectionInputIntrospector implements InputIntrospector
{
    public function __construct(
        private ProjectContext $context
    ) {
    }

    public function introspect(Node $node): array
    {
        return [
            'inputs' => $this->inputsFor($node),
            'return' => $this->returnTypeFor($node),
        ];
    }

    private function inputsFor(Node $node): array
    {
        $method = $this->reflectMethod($node);

        $inputs = [];

        foreach ($method->getParameters() as $param) {
            $inputs[] = [
                'name' => $param->getName(),
                'type' => $this->normalizeType($param->getType()),
                'required' => !$param->isOptional(),
                'default' => $param->isDefaultValueAvailable()
                    ? $param->getDefaultValue()
                    : null,
            ];
        }

        return $inputs;
    }

    private function returnTypeFor(Node $node): string
    {
        $method = $this->reflectMethod($node);
        return $this->normalizeType($method->getReturnType());
    }

    private function reflectMethod(Node $node): \ReflectionMethod
    {
        $this->context->boot();

        $class = $this->resolveFqn($node);

        return (new \ReflectionClass($class))
            ->getMethod($node->method());
    }

    private function resolveFqn(Node $node): string
    {
        $code = file_get_contents($node->file());

        if (preg_match('/namespace\s+([^;]+);/', $code, $m)) {
            return $m[1] . '\\' . $node->class();
        }

        return $node->class();
    }

    private function normalizeType(?\ReflectionType $type): string
    {
        if ($type === null) {
            return 'mixed';
        }

        if ($type instanceof \ReflectionNamedType) {
            return $type->getName();
        }

        if ($type instanceof \ReflectionUnionType) {
            return implode('|', array_map(
                fn($t) => $t->getName(),
                $type->getTypes()
            ));
        }

        if ($type instanceof \ReflectionIntersectionType) {
            return implode('&', array_map(
                fn($t) => $t->getName(),
                $type->getTypes()
            ));
        }

        return 'mixed';
    }
}
