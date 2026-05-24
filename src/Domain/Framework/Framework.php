<?php

namespace FlowEngine\Domain\Framework;

use FlowEngine\Domain\Flow\Node;
use FlowEngine\Domain\Node\NodeVisibility;

final class Framework
{
    public const LARAVEL = 'laravel';
    public const WORDPRESS = 'wordpress';
    public const COMPOSER = 'composer';
    public const CUSTOM = 'custom';

    private function __construct(
        private string $value
    ) {
    }

    public static function laravel(): self
    {
        return new self(self::LARAVEL);
    }

    public static function wordpress(): self
    {
        return new self(self::WORDPRESS);
    }

    public static function composer(): self
    {
        return new self(self::COMPOSER);
    }

    public static function custom(): self
    {
        return new self(self::CUSTOM);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function is(self $framework): bool
    {
        return $this->value === $framework->value;
    }

    /**
     * Regra default de visibilidade por framework
     */
    public function defaultVisibilityFor(Node $node): NodeVisibility
    {
        if (
            $this->is(self::laravel())
            && str_starts_with($node->class(), 'Illuminate\\')
        ) {
            return new NodeVisibility(NodeVisibility::HIDDEN);
        }

        return new NodeVisibility(NodeVisibility::PUBLIC);
    }
}
