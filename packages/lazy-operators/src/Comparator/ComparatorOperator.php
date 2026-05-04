<?php

declare(strict_types=1);

namespace PhpArchitecture\LazyOperators\Comparator;

use PhpArchitecture\LazyOperators\Expression;

interface ComparatorOperator extends Expression
{
    public function __invoke(): float|int;
}
