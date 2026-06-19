<?php

namespace Fixture;

final class Calculator
{
    public function sum(int $left, int $right): int
    {
        return $left + $right;
    }

    public function multiply(int $left, int $right): int
    {
        return $left * $right;
    }
}
