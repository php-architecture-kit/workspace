<?php

declare(strict_types=1);

namespace PhpArchitecture\LazyOperators\Arithmetic;

use PhpArchitecture\LazyOperators\Expression;

interface ArithmeticOperator extends Expression
{
    public function __invoke(): float|int;
}
