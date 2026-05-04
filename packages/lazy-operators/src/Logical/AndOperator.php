<?php

declare(strict_types=1);

namespace PhpArchitecture\LazyOperators\Logical;

use PhpArchitecture\LazyOperators\Expression;

class AndOperator implements LogicalOperator
{
    public function __construct(
        private readonly Expression $left,
        private readonly Expression $right,
    ) {
    }
    
    public function __invoke(): bool
    {
        return ($this->left)() && ($this->right)();
    }
}
