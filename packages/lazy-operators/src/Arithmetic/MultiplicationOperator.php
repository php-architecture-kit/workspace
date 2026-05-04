<?php

declare(strict_types=1);

namespace PhpArchitecture\LazyOperators\Arithmetic;

use PhpArchitecture\LazyOperators\Expression;

class MultiplicationOperator implements ArithmeticOperator
{
    public function __construct(
        private readonly Expression $left,
        private readonly Expression $right,
    ) {}

    public function __invoke(): float|int
    {
        return ($this->left)() * ($this->right)();
    }
}
