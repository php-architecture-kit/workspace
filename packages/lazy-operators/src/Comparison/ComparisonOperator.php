<?php

declare(strict_types=1);

namespace PhpArchitecture\LazyOperators\Comparison;

use PhpArchitecture\LazyOperators\Expression;

interface ComparisonOperator extends Expression
{
    public function __invoke(): bool;
}
