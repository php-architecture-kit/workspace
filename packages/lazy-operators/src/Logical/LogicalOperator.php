<?php

declare(strict_types=1);

namespace PhpArchitecture\LazyOperators\Logical;

use PhpArchitecture\LazyOperators\Expression;

interface LogicalOperator extends Expression
{
    public function __invoke(): bool;
}
