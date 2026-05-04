<?php

declare(strict_types=1);

namespace PhpArchitecture\LazyOperators\Static;

use PhpArchitecture\LazyOperators\Expression;

class Value implements Expression
{
    public function __construct(
        private readonly mixed $value,
    ) {
    }
    
    public function __invoke(): mixed
    {
        return $this->value;
    }
}
