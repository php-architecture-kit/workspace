<?php

declare(strict_types=1);

namespace PhpArchitecture\LazyOperators\Comparator;

use PhpArchitecture\LazyOperators\Expression;

class SpaceshipOperator implements ComparatorOperator
{
    public function __construct(
        private readonly Expression $left,
        private readonly Expression $right,
    ) {}

    public function __invoke(): float|int
    {
        return ($this->left)() <=> ($this->right)();
    }
}
