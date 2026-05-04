<?php

declare(strict_types=1);

namespace PhpArchitecture\LazyOperators\Conditional;

use PhpArchitecture\LazyOperators\Expression;

class IfElseOperator implements Expression
{
    public function __construct(
        private readonly Expression $condition,
        private readonly Expression $then,
        private readonly Expression $else,
    ) {}

    public function __invoke(): mixed
    {
        return ($this->condition)() ? ($this->then)() : ($this->else)();
    }
}
