<?php

namespace FlowEngine\Domain\Node;

final class NodeVisibility
{
    public const PUBLIC = 'public';
    public const HIDDEN = 'hidden';

    public function __construct(
        private string $value
    ) {
        if (!in_array($value, [self::PUBLIC , self::HIDDEN], true)) {
            throw new \InvalidArgumentException("Invalid visibility: {$value}");
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isPublic(): bool
    {
        return $this->value === self::PUBLIC;
    }
}
