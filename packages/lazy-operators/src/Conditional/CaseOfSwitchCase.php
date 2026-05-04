<?php

declare(strict_types=1);

namespace PhpArchitecture\LazyOperators\Conditional;

use PhpArchitecture\LazyOperators\Expression;

class CaseOfSwitchCase
{
    public function __construct(
        public readonly Expression $condition,
        public readonly Expression $value,
    ) {}
}
